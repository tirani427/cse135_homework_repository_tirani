#!/usr/bin/python3

import os
from pathlib import Path
from http import cookies
from urllib.parse import parse_qs

SESSION_DIR = Path("/tmp")
COOKIE_NAME = "CGISESSID"
FILE_PREFIX = "pycgi_session_"

def load_cookie():
    c = cookies.SimpleCookie()
    c.load(os.environ.get("HTTP_COOKIE", ""))
    return c

def get_query_params():
    qs = os.environ.get("QUERY_STRING", "")
    return parse_qs(qs, keep_blank_values=True)

def session_file(sid: str) -> Path:
    return SESSION_DIR/f"{FILE_PREFIX}{sid}.json"

cookiejar = load_cookie()
params = get_query_params()

sid = None
if COOKIE_NAME in cookiejar:
    sid = cookiejar[COOKIE_NAME].value
elif "sid" in params and params["sid"]:
    sid = params["sid"][0]

if sid:
    f = session_file(sid)
    try:
        if f.exists():
            f.unlink()
    except Exception:
        pass

expire_cookie = cookies.SimpleCookie()
expire_cookie[COOKIE_NAME] = ""
expire_cookie[COOKIE_NAME]["path"] = "/"
expire_cookie[COOKIE_NAME]["max-age"] = 0

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=utf-8")
print(expire_cookie.output().strip())
print("")

print("""<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Python Session Destroyed</title>
    </head>
    <body>
        <h1>Session Destroyed</h1>
        <a href="/python-cgiform.html">Back to the Python CGI Form </a><br/>
        <a href="./python-sessions-1.py">Back to Page 1</a<br/>
        <a href="./python-sessions-2.py">Back to Page 2</a><br/>
    </body>
</html>
""")
