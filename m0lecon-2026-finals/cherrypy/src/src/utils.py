import os
import json
from urllib.parse import parse_qs
import cherrypy


def load_creds(creds_path):
    if not os.path.exists(creds_path):
        raise RuntimeError(f"Missing credentials file: {creds_path}")

    with open(creds_path, "r", encoding="utf-8") as f:
        for raw in f:
            line = raw.strip()
            if ":" not in line:
                raise RuntimeError("creds.txt must be 'username:password'")
            user, pw = line.split(":", 1)
            user, pw = user.strip(), pw.strip()
            if not user or not pw:
                raise RuntimeError("creds.txt must be 'username:password'")
            return user, pw

    raise RuntimeError("creds.txt has no usable credentials")


def require_login(api=False):
    try:
        if cherrypy.session.get("user"):
            return
        if api or cherrypy.request.path_info.startswith("/api/"):
            raise cherrypy.HTTPError(401, "Unauthorized")
    except cherrypy.HTTPError:
        raise
    except Exception:
        raise cherrypy.HTTPError(500, "Session error")

    raise cherrypy.HTTPRedirect("/static/login.html")


def parse_request_body(**kwargs):
    content_type = cherrypy.request.headers.get("Content-Type", "")

    if "application/json" in content_type:
        raw_body = cherrypy.request.body.read()
        try:
            return json.loads(raw_body)
        except json.JSONDecodeError:
            raise cherrypy.HTTPError(400, "Invalid JSON")

    if "multipart/form-data" in content_type:
        data = {}
        for key, value in kwargs.items():
            if hasattr(value, "file"):
                file_content = value.file.read()
                try:
                    data[key] = json.loads(file_content)
                except json.JSONDecodeError:
                    data[key] = file_content.decode("utf-8", errors="replace")
                except Exception:
                    return ""
            else:
                try:
                    data[key] = json.loads(value)
                except (json.JSONDecodeError, TypeError):
                    data[key] = value
        return data

    if "application/x-www-form-urlencoded" in content_type:
        raw_body = cherrypy.request.body.read().decode("utf-8")
        parsed = parse_qs(raw_body, keep_blank_values=True)
        return {
            key: (values[0] if len(values) == 1 else values)
            if not _try_json(values[0])[0]
            else _try_json(values[0])[1]
            for key, values in parsed.items()
        }

    raw_body = cherrypy.request.body.read()
    try:
        return json.loads(raw_body)
    except json.JSONDecodeError:
        raise cherrypy.HTTPError(400, "Unsupported content type or invalid JSON")


def _try_json(val):
    try:
        return True, json.loads(val)
    except (json.JSONDecodeError, TypeError):
        return False, val


def get_request_data(**kwargs):
    return getattr(cherrypy.request, "parsed_body", None) or parse_request_body(
        **kwargs
    )


def _parse_body_tool():
    cherrypy.request.parsed_body = parse_request_body()


def register_tools():
    cherrypy.tools.parse_body = cherrypy.Tool(
        "before_handler", _parse_body_tool, priority=50
    )
    cherrypy.tools.require_login = cherrypy.Tool(
        "before_handler", require_login, priority=60
    )


class Config:
    DEFAULTS = {
        "refresh_interval": 10,
        "theme": "sunrise",
        "alerts_enabled": True,
        "max_items": 25,
        "banner_message": "Welcome to CherryPi",
    }

    def __init__(self, **overrides):
        for key, default in self.DEFAULTS.items():
            setattr(self, key, overrides.get(key, default))

    def to_dict(self):
        return {key: getattr(self, key) for key in self.DEFAULTS}

    def update(self, src, dst=None):
        if dst is None:
            dst = self
        for k, v in src.items():
            if isinstance(dst, dict):
                if dst.get(k) is not None and isinstance(v, dict):
                    self.update(v, dst.get(k))
                else:
                    dst[k] = v
            elif hasattr(dst, k) and isinstance(v, dict):
                self.update(v, getattr(dst, k))
            else:
                setattr(dst, k, v)
