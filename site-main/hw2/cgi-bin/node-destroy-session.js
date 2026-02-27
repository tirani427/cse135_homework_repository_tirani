#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const SESSION_DIR = "/tmp";
const COOKIE_NAME = "CGISESSID";

const cookies = parseCookies(process.env.HTTP_COOKIE || "");
const sid = cookies[COOKIE_NAME] || null;

if (sid) {
  const sessionPath = path.join(SESSION_DIR, `nodecgi_session_${sid}.json`);
  try { if (fs.existsSync(sessionPath)) fs.unlinkSync(sessionPath); } catch {}
}

console.log("Cache-Control: no-cache");
console.log("Content-Type: text/html; charset=utf-8");
console.log(`Set-Cookie: ${COOKIE_NAME}=deleted; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly`);
console.log("");

console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Session Destroyed</title></head>
<body>
  <h1>Session Destroyed</h1>
  <a href="/python-cgiform.html">Back to Form</a><br/>
  <a href="./node-sessions-1.js">Back to Page 1</a><br/>
  <a href="./node-sessions-2.js">Back to Page 2</a>
</body>
</html>`);

function parseCookies(cookieHeader) {
  const out = {};
  if (!cookieHeader) return out;
  cookieHeader.split(";").forEach((part) => {
    const [k, ...rest] = part.trim().split("=");
    if (!k) return;
    out[k] = decodeURIComponent(rest.join("=") || "");
  });
  return out;
}

