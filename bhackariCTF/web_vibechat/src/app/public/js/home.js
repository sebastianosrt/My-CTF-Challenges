(function () {
  function readInitialState() {
    const stateElement = document.getElementById('vibechat-state');
    if (!stateElement) {
      return {};
    }

    try {
      let state = JSON.parse(stateElement.textContent);
      stateElement.remove();
      return state;
    } catch (err) {
      return {};
    }
  }

  const state = readInitialState();
  const username = typeof state.username === 'string' ? state.username : '';
  const DOMPURIFY_CONFIG = getDomPurifyConfig();
  const chatLog = document.getElementById('chat-log');
  const chatForm = document.getElementById('chat-form');
  const chatInput = document.getElementById('chat-input');
  const modal = document.getElementById('settings-modal');
  const openSettingsButton = document.getElementById('open-settings');
  const closeSettingsButton = document.getElementById('close-settings');
  const apiKeyInput = document.getElementById('api-key');
  const apiKeyDisplay = document.getElementById('current-api-key');
  let currentApiKey = typeof state.apiKey === 'string' ? state.apiKey : '';

  // chatgpt, I completely trust you
  function getDomPurifyConfig() {
    return {
      ALLOWED_TAGS: [
        'a', 'abbr', 'article', 'aside', 'audio', 'b', 'bdi', 'bdo', 'blockquote', 'body', 'br', 'button', 'caption', 'cite', 'code', 'col', 'colgroup', 'data', 'datalist', 'dd', 'details', 'dfn', 'div', 'dl', 'dt', 'em', 'fieldset', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html', 'i', 'img', 'input', 'kbd', 'label', 'legend', 'li', 'main', 'mark', 'meta', 'meter', 'nav', 'ol', 'optgroup', 'option', 'output', 'p', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'select', 'small', 'source', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'u', 'ul', 'var', 'video', 'wbr'
      ],
      ALLOWED_ATTR: [
        'accept', 'accept-charset', 'accesskey', 'align', 'alt', 'aria-activedescendant', 'aria-atomic', 'aria-autocomplete', 'aria-busy', 'aria-checked', 'aria-colcount', 'aria-colindex', 'aria-colspan', 'aria-controls', 'aria-current', 'aria-describedby', 'aria-details', 'aria-disabled', 'aria-dropeffect', 'aria-errormessage', 'aria-expanded', 'aria-flowto', 'aria-grabbed', 'aria-haspopup', 'aria-hidden', 'aria-invalid', 'aria-keyshortcuts', 'aria-label', 'aria-labelledby', 'aria-level', 'aria-live', 'aria-modal', 'aria-multiline', 'aria-multiselectable', 'aria-orientation', 'aria-owns', 'aria-placeholder', 'aria-posinset', 'aria-pressed', 'aria-readonly', 'aria-relevant', 'aria-required', 'aria-roledescription', 'aria-rowcount', 'aria-rowindex', 'aria-rowspan', 'aria-selected', 'aria-setsize', 'aria-sort', 'aria-valuemax', 'aria-valuemin', 'aria-valuenow', 'aria-valuetext', 'autocapitalize', 'autocomplete', 'autofocus', 'autoplay', 'charset', 'checked', 'cite', 'class', 'cols', 'colspan', 'content', 'contenteditable', 'controls', 'coords', 'crossorigin', 'data-*', 'datetime', 'decoding', 'default', 'dir', 'dirname', 'disabled', 'download', 'draggable', 'enterkeyhint', 'for', 'form', 'formenctype', 'formmethod', 'formnovalidate', 'headers', 'height', 'hidden', 'high', 'href', 'hreflang', 'http-equiv', 'id', 'inert', 'inputmode', 'integrity', 'ismap', 'itemid', 'itemprop', 'itemref', 'itemscope', 'itemtype', 'kind', 'label', 'lang', 'list', 'loading', 'loop', 'low', 'max', 'maxlength', 'media', 'min', 'minlength', 'multiple', 'muted', 'name', 'novalidate', 'open', 'optimum', 'pattern', 'ping', 'placeholder', 'playsinline', 'poster', 'preload', 'readonly', 'rel', 'required', 'reversed', 'role', 'rows', 'rowspan', 'sandbox', 'scope', 'scoped', 'selected', 'shape', 'size', 'sizes', 'slot', 'span', 'spellcheck', 'src', 'srcdoc', 'srclang', 'srcset', 'start', 'step', 'style', 'tabindex', 'title', 'translate', 'type', 'usemap', 'value', 'width'
      ],
      ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|sms|cid|xmpp|matrix):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
    };
  }

  function sanitize(message) {
    return DOMPurify.sanitize(message, DOMPURIFY_CONFIG);
  }

  function appendMessage(content) {
    const entry = document.createElement('li');
    entry.innerHTML = sanitize(content);
    chatLog.appendChild(entry);
  }

  function openModal() {
    apiKeyInput.value = currentApiKey;
    apiKeyInput.addEventListener('focus', (event) => {
      apiKeyInput.value = '';
    })
    apiKeyDisplay.textContent = currentApiKey || '(not set)';
    modal.classList.add('active');
  }

  function closeModal() {
    modal.classList.remove('active');
  }

  openSettingsButton.addEventListener('click', openModal);
  closeSettingsButton.addEventListener('click', closeModal);

  chatForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const message = chatInput.value.trim();
    if (!message) {
      return;
    }
    appendMessage('<strong>' + username + '</strong>: ' + message);
    chatInput.value = '';
  });

  function requestJsonp(callbackName) {
    fetch('/jsonp?callback=' + encodeURIComponent(callbackName)).then(r => r.text()).then(r => {
      if (r.includes('error')) {
        appendMessage(r);
      } else {
        eval(r);
      }
    });
  }

  window.showSettings = function (payload) {
    if (payload && payload.error) {
      appendMessage(payload.error);
      return;
    }
    openModal();
  };

  const params = new URLSearchParams(window.location.search);
  requestJsonp(params.get('settings'));


  apiKeyInput.value = currentApiKey;
  apiKeyDisplay.textContent = currentApiKey || '(not set)';

  appendMessage('Welcome to the chat, ' + username + '!');
})();
