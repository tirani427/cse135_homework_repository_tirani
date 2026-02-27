#!/usr/bin/python3

import os, json
from pathlib import Path
from http import cookies

SESSION_DIR = Path("/tmp")
COOKIE_NAME = "CGISESSID"

def load_cookie():
    c = cookies.SimpleCookie()
    c.load(os.environ.get("HTTP_COOKIE", ""))
    return c

def session_file(sid: str) -> Path:
    return SESSION_DIR /f"pycgi_session_{sid}.json"

def load_session(sid: str) -> dict:
    if not sid:
        return {}
    f = session_file(sid)
    if not f.exists():
        return {}
    try:
        return json.loads(f.read_text())
    except Exception:
        return {}

cookiejar = load_cookie()
sid = cookiejar[COOKIE_NAME].value if COOKIE_NAME in cookiejar else None

session = load_session(sid)
name = session.get("username")

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=utf-8")
print("")

print(""" <!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Python Sessions</title>
    </head>

    <body>
        <h1>Python Sessions Page 2</h1>
""")

if name:
    print(" <p><b>Name:</b> "+ name + "</p>")
else:
    print("<p><b>Name:</b> You do not have a name set</p>")
print(""" <br/><br/>
        <a href="./python-sessions-1.py">Session Page 1</a><br/>
        <a href="/python-cgiform.html">Python CGI Form</a><br/>
        <form style="margin-top:30px" action="./python-destroy-session.py" method="get">
            <button type="submit">Destroy Session</button>
        </form>
    </body>
</html>
""")
