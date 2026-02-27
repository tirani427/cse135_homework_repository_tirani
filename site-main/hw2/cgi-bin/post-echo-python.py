#!/usr/bin/python3

import os, sys
from urllib.parse import parse_qs

print("Content-Type: text/html")
print("")

try:
    length = int(os.environ.get("CONTENT_LENGTH", "0"))
except ValueError:
    length = 0

body = sys.stdin.read(length) if length > 0 else ""

params = parse_qs(body, keep_blank_values=True);

print("""
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Python POST Echo</title>
    </head>

    <body>
        <h1>Python POST Request Echo</h1>
        <p><b>Message Body:</b></p>
        <ul>
""")

for key, values in params.items():
    for value in values:
        print("<li>" + key + " = " + value + "</li>")

print("""
      </ul>
    </body>
</html>
""")
