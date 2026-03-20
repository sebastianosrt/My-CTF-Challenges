const fs = require('fs');
const path = require('path');
const { escapeHtml } = require('./utils');
const { TIMEZONE_OPTIONS } = require('./config');
const { listUserEvents, getUserConfig, defaultUserConfig } = require('./state');
const {
  formatDayInput,
  formatTimeInput,
  formatDateLabel,
  formatTimeLabel
} = require('./datetime');

const loadPage = (name) => fs.readFileSync(path.join(__dirname, '..', 'pages', name), 'utf8');
const INDEX_PAGE = loadPage('index.html');
const EVENT_PAGE = loadPage('event.html');
const CALENDAR_PAGE = loadPage('calendar.html');
const SETTINGS_PAGE = loadPage('settings.html');

function renderIndex(error) {
  const err = error ? `<div class="error">${escapeHtml(error)}</div>` : '';
  return INDEX_PAGE.replace('{{error}}', err);
}

function renderCalendar(req) {
  const config = getUserConfig(req.user);
  const userEvents = listUserEvents(req.user.username, config);

  const rows = userEvents.length
    ? userEvents.map((event) => {
      const id = encodeURIComponent(event.id);
      const timeRange = `${formatTimeLabel(event.startAt, config)}-${formatTimeLabel(event.endAt, config)}`;
      return `<div class="event">
        <div>
          <a href="/events/${id}">${escapeHtml(event.title)}</a>
          <div class="event-meta">${escapeHtml(formatDateLabel(event.startAt, config))} &middot; ${escapeHtml(timeRange)}</div>
        </div>
        <div class="event-actions">
          <form method="POST" action="/report" class="inline-form">
            <input type="hidden" name="eventId" value="${escapeHtml(event.id)}" />
            <button type="submit">report</button>
          </form>
          <form method="POST" action="/events/${id}/delete" class="inline-form">
            <button type="submit" class="btn-danger">x</button>
          </form>
        </div>
      </div>`;
    }).join('')
    : '<em>No events scheduled.</em>';

  const msg = req.query.msg
    ? `<div class="msg">${escapeHtml(req.query.msg)}</div>`
    : '';

  const html = CALENDAR_PAGE
    .replace('{{username}}', escapeHtml(req.user.username))
    .replace('{{message}}', msg)
    .replace('{{timezone}}', escapeHtml(config.timezone))
    .replace('{{notesTemplate}}', escapeHtml(config.notesTemplate))
    .replace('{{events}}', rows);
  return html;
}

function renderSettings(req) {
  const config = getUserConfig(req.user);
  const msg = req.query.msg
    ? `<div class="msg">${escapeHtml(req.query.msg)}</div>`
    : '';

  const timezoneOptions = TIMEZONE_OPTIONS
    .map((timezone) => {
      const selected = timezone === config.timezone ? ' selected' : '';
      return `<option value="${escapeHtml(timezone)}"${selected}>${escapeHtml(timezone)}</option>`;
    })
    .join('');

  const html = SETTINGS_PAGE
    .replace('{{username}}', escapeHtml(req.user.username))
    .replace('{{message}}', msg)
    .replace('{{timezoneOptions}}', timezoneOptions)
    .replace('{{showPastChecked}}', config.showPastEvents ? 'checked' : '')
    .replace('{{notesTemplate}}', escapeHtml(config.notesTemplate));
  return html;
}

function renderEvent(event, req) {
  const canEdit = !!req.user && req.user.username === event.owner;
  const config = req.user ? getUserConfig(req.user) : defaultUserConfig();
  const id = encodeURIComponent(event.id);
  const schedule = `${formatDateLabel(event.startAt, config)} ${formatTimeLabel(event.startAt, config)}-${formatTimeLabel(event.endAt, config)} (${config.timezone})`;
  const msg = req.query.msg
    ? `<div class="msg">${escapeHtml(req.query.msg)}</div>`
    : '';

  const reportForm = `<form method="POST" action="/report" class="inline-form">
    <input type="hidden" name="eventId" value="${escapeHtml(event.id)}" />
    <button type="submit">report</button>
  </form>`;

  const editor = canEdit
    ? `<div class="section">
      <h3>Edit Event</h3>
      <form method="POST" action="/events/${id}/edit">
        <input name="title" value="${escapeHtml(event.title)}" required />
        <div class="time-grid">
          <label>Date <input type="date" name="day" value="${escapeHtml(formatDayInput(event.startAt))}" required /></label>
          <label>Start <input type="time" name="start" value="${escapeHtml(formatTimeInput(event.startAt))}" required /></label>
          <label>End <input type="time" name="end" value="${escapeHtml(formatTimeInput(event.endAt))}" required /></label>
        </div>
        <textarea name="notes" placeholder="notes">${escapeHtml(event.notes)}</textarea>
        <button type="submit">Save</button>
      </form>
    </div>`
    : '';

  const html = EVENT_PAGE
    .replace(/\{\{title\}\}/g, escapeHtml(event.title))
    .replace('{{schedule}}', escapeHtml(schedule))
    .replace('{{owner}}', escapeHtml(event.owner))
    .replace('{{message}}', msg)
    .replace('{{report}}', reportForm)
    .replace('{{editor}}', editor)
    .replace('{{notes}}', event.notes);
  return html;
}

module.exports = {
  renderIndex,
  renderCalendar,
  renderSettings,
  renderEvent
};
