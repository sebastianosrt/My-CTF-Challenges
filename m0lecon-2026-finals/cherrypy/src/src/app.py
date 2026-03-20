import os
import threading
import cherrypy
from utils import load_creds, get_request_data, register_tools, Config

register_tools()


class Root:
    @cherrypy.expose
    def index(self):
        if cherrypy.session.get("user"):
            raise cherrypy.HTTPRedirect("/static/index.html")
        raise cherrypy.HTTPRedirect("/static/login.html")

    @cherrypy.expose
    def health(self):
        return "ok"


class LoginAPI:
    exposed = True

    def __init__(self, creds_path):
        self._creds_path = creds_path

    @cherrypy.tools.parse_body()
    @cherrypy.tools.json_out()
    def POST(self, **kwargs):
        data = get_request_data(**kwargs)
        if not isinstance(data, dict):
            raise cherrypy.HTTPError(400, "Body must be an object")

        username, password = data.get("username"), data.get("password")
        if not isinstance(username, str) or not isinstance(password, str):
            raise cherrypy.HTTPError(400, "username/password required")

        expected_user, expected_pw = load_creds(self._creds_path)
        if username != expected_user or password != expected_pw:
            raise cherrypy.HTTPError(401, "Invalid credentials")

        cherrypy.session["user"] = username
        return {"ok": True, "user": username}


class LogoutAPI:
    exposed = True

    @cherrypy.tools.json_out()
    def POST(self):
        cherrypy.session.pop("user", None)
        return {"ok": True}


class ConfigAPI:
    exposed = True

    def __init__(self, config, lock):
        self._config = config
        self._lock = lock

    @cherrypy.tools.json_out()
    def GET(self):
        with self._lock:
            return {"ok": True, "config": self._config.to_dict()}

    @cherrypy.tools.parse_body()
    @cherrypy.tools.json_out()
    def POST(self, **kwargs):
        data = get_request_data(**kwargs)
        if not isinstance(data, dict):
            raise cherrypy.HTTPError(400, "Body must be an object")

        try:
            self._config.update(data)
        except Exception as e:
            cherrypy.response.status = 400
            return {"ok": False, "error": str(e), "config": self._config.to_dict()}

        return {"ok": True, "config": self._config.to_dict()}


def api_route(require_auth=False):
    cfg = {"request.dispatch": cherrypy.dispatch.MethodDispatcher()}
    if require_auth:
        cfg["tools.require_login.on"] = True
        cfg["tools.require_login.api"] = True
    return {"/": cfg}


def create_app(host="127.0.0.1", port=1337):
    base_dir = os.path.abspath(os.path.dirname(__file__))
    static_dir = os.path.join(base_dir, "static")
    creds_path = os.path.join(base_dir, "static_but_private", "creds.txt")
    sessions_dir = os.path.join(base_dir, "sessions")
    os.makedirs(sessions_dir, exist_ok=True)

    config = Config()
    lock = threading.Lock()

    cherrypy.config.update(
        {
            "server.socket_host": host,
            "server.socket_port": int(port),
            "tools.encode.on": True,
            "tools.encode.encoding": "utf-8",
            "tools.sessions.on": True,
            "tools.sessions.storage_type": "file",
            "tools.sessions.storage_path": sessions_dir,
            "tools.sessions.timeout": 60,
            "tools.response_headers.on": True,
            "tools.response_headers.headers": [("Cache-Control", "no-store")],
        }
    )

    cherrypy.tree.mount(
        Root(),
        "/",
        {
            "/static": {"tools.staticdir.on": True, "tools.staticdir.dir": static_dir},
        },
    )
    cherrypy.tree.mount(LoginAPI(creds_path), "/api/login", api_route())
    cherrypy.tree.mount(LogoutAPI(), "/api/logout", api_route(require_auth=True))
    cherrypy.tree.mount(
        ConfigAPI(config, lock), "/api/config", api_route(require_auth=True)
    )


def run(host="127.0.0.1", port=1337):
    create_app(host, port)
    cherrypy.engine.start()
    cherrypy.engine.block()


if __name__ == "__main__":
    run(
        host=os.environ.get("HOST", "127.0.0.1"),
        port=int(os.environ.get("PORT", "1337")),
    )
