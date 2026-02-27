#!/usr/bin/env node

const date = new Date().toString();
const ip = process.env.REMOTE_ADDR || "unknown";

console.log("Cache-Control: no-cache");
console.log("Content-Type: text/html; charset=utf-8\n");

console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Hello CGI World</title></head>
<body>
  <h1 style="text-align:center;">Hello HTML World</h1><hr/>
  <p>Hello World</p>
  <p>This page was generated with the Node.js programming language</p>
  <p>This program was generated at: ${date}</p>
  <p>Your current IP Address is: ${ip}</p>
</body>
</html>`);
