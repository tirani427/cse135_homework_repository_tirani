#!/usr/bin/python3

print("Content-Type: text/html\n")

import os

env = os.environ

print("""<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Python Environment Variables</title>
    </head>
    <body>
        <h1>Environment Variables</h1>
        <hr/>
        <ul>
""")

for key, value in env.items():
    print("<li> <b>"+key+"</b>: " + value + "</li>")

print("</ul> </body> </html>")

