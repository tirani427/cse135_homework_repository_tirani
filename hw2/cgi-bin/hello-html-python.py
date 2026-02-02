#!/usr/bin/python3

print("Content-Type: text/html\n")

print("""<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hello Python CGI World</title>
    <style>
        h1{
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Hello from Python CGI!</h1>
    <hr/>
    <h2>Hello World</h2>
    <p> Tia says hi :) </p>
    <script>
        document.write("This page was generated at ", new Date().toLocaleString());
    </script>
</body>
</html>
""")
