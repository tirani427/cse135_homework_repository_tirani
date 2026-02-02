#!/usr/bin/python3

print("Content-Type: text/html\n")

import os
from urllib.parse import parse_qs

query = os.environ.get("QUERY_STRING", "")
params = parse_qs(query)

print(""" <!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title> Python Get Echo </title>
    </head>
    <body>
        <h1> Python GET Echo </h1>
        <p>Query String:""" + query + """</p>
""")

for key, values in params.items():
    for value in values:
        print("<p>" + key + " = " + value + "</p>")

print("</body></html>")

