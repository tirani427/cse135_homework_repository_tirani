#!/usr/bin/python3



import os, json, uuid, time
from http import cookies
from pathlib import Path
from datetime import datetime
from urllib.parse import parse_qs

SESSION_DIR = Path("/tmp")
#SESSION_DIR.mkdir(parents=True, exist_ok=True)
COOKIE_NAME = "CGISESSID"

def load_cookie():
    c = cookies.SimpleCookie()
    c.load(os.environ.get("HTTP_COOKIE", ""))
    return c

def get_method():
    return os.environ.get("REQUEST_METHOD", "GET").upper()

def get_query_params():
    qs = os.environ.get("QUERY_STRING", "")
    return {k: v for k, v in parse_qs(qs, keep_blank_values=True).items()}

def read_body_params():
    try:
        length = int(os.environ.get("CONTENT_LENGTH", "0"))
    except ValueError:
        length = 0
    body = os.environ.get("wsgi.input", None)
    data = os.sys.stdin.read(length) if length > 0 else ""
    return {k:v for k, v in parse_qs(data, keep_blank_values=True).items()}

def first(params, key):
    v = params.get(key)
    if not v:
        return None
    return v[0]

def session_file(sid: str) -> Path:
    return SESSION_DIR/f"pycgi_session_{sid}.json"

def load_session(sid: str) -> dict:
    f = session_file(sid)
    if not f.exists():
        return {}
    try:
        return json.loads(f.read_text())
    except Exception:
        return {}

def save_session(sid, data):
    f = session_file(sid)
    f.write_text(json.dumps(data))


cookiejar = load_cookie()
sid = cookiejar[COOKIE_NAME].value if COOKIE_NAME in cookiejar else None

new_session = False
if not sid:
    sid = uuid.uuid4().hex
    new_session = True

session = load_session(sid)

params = get_query_params()

incoming_name = first(params, "username") or ""

incoming_name = incoming_name.strip()
if incoming_name:
    session["username"] = incoming_name

name = session.get("username", "")

save_session(sid, session)

set_cookie = cookies.SimpleCookie()
set_cookie[COOKIE_NAME] = sid
set_cookie[COOKIE_NAME]["path"] = "/"


print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=utf-8")
if new_session:
    print(set_cookie.output().strip())

print("")

print("""
<!DOCTYPE html>
    <head>
        <meta charset="utf-8">
        <title>Python CGI Session</title>
    </head>

    <body>
        <h1> Python CGI Session Page 1</h1>
""")
if name:
    print(" <p><b>Name:</b> " + name + "</p>")
else:
    print("<p><b>Name:</b> You do not have a name set </p>")

print(""" <br /><br />
        <a href="./python-sessions-2.py">Session Page 2</a><br/>
        <a href="/python-cgiform.html">Python CGI Form </a><br />
        <form stle="margin-top:30px" action="./python-destroy-session.py" method="get">
            <button type="submit">Destroy Session</button>
        </form>
    </body>
</html>
""")
