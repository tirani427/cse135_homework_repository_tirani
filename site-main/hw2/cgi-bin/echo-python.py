#!/usr/bin/python3

# print("Content-Type: text/html\n")

import os, sys, json, socket, urllib.parse, datetime

def read_body():
    try:
        length = int(os.environ.get("CONTENT_LENGTH", "0"))
    except ValueError:
        length = 0
    return sys.stdin.read(length) if length > 0 else ""

method = os.environ.get("REQUEST_METHOD", "GET")
query = os.environ.get("QUERY_STRING", "")
content_type = os.environ.get("CONTENT_TYPE", "")

raw_body  = read_body()

params = {}

if method == "GET":
    params = {key:value for key,value in urllib.parse.parse_qs(query, keep_blank_values=True).items()}
else:
    if content_type.startswith("application/json"):
        try:
            params = json.loads(raw_body) if raw_body else {}
        except json.JSONDecodeError:
            params = {"_json_error": "invalid json", "_raw": raw_body}
    else:
        params = {key:value for key,value in urllib.parse.parse_qs(raw_body, keep_blank_values=True).items()}

ip = os.environ.get("REMOTE_ADDR", "unknown")
user_agent = os.environ.get("HTTP_USER_AGENT", "unknown")
host = socket.gethostname()
time = datetime.datetime.now().isoformat()

response = {
        "endpoint":"echo-python",
        "method":method,
        "content_type":content_type,
        "query_string":query,
        "params":params,
        "raw_body":raw_body,
        "meta": {
            "hostname": host,
            "time": time,
            "user_agent":user_agent,
            "ip": ip
            }
        }

print("Cache-Control: no-cache")

if(content_type.startswith("application/json")):
    print("Content-Type: application/json")
    print("")
    print(json.dumps(response, indent=1))
else:
    print("Content-Type: text/html")
    print("")

    def one(x):
        if x is None:
            return ""
        if isinstance(x, list):
            return x[0] if x else ""
        return str(x)

    name = one(params.get("name"))
    message = one(params.get("message"))

    print("""<!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8">
        </head>
        <body>
            <h2>Echo (x-www-form-urlencoded)</h2>
            <ul>
                <li>name = """ + name + """</li>
                <li>message = """ + message + """</li>
            </ul>

            <p><b>Method:</b> """ + method + """</p>
            <p><b>Content-Type:</b> """ + content_type + """</p>
            <p><b>Query-String:</b> """ + query + """ </p>
            <p><b>IP:</b> """ + ip + """</p>
            <p><b>User-Agent:</b> """ + user_agent + """</p>
            <p><b>Time:</b> """ + time + """</p>
    
       </body>
    </html>
    """)
