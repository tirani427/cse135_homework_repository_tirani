#!/usr/bin/python3

import json 
import datetime

print("Content-Type: application/json")
print("")

date = datetime.datetime.now().isoformat()

data = {
        "message": "This message was generated with Python CGI!",
        "date": date,
        "title": "Hello Python!",
        "heading": "Hello Python!!",
        "personalization": "Tia says hi :)"
        }

print(json.dumps(data))

