#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

const SESSION_DIR = "/tmp";
const COOKIE_NAME = "CGISESSID";

const cookies = parseCookies(process.env.HTTP_COOKIE || "");
const sid = cookies[COOKIE_NAME] || null;

let username = "";
if (sid) {
  const sessionPath = path.join(SESSION_DIR, `nodecgi_session_${sid}.json`);
  try {
    if (fs.existsSync(sessionPath)) {
      const s = JSON.parse(fs.readFileSync(sessionPath, "utf8"));
      username = s.username || "";
    }
  } catch {}
}

console.log("Cache-Control: no-cache");
console.log("Content-Type: text/html; charset=utf-8\n");

console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Node Sessions</title></head>
<body>
  <h1>Node Sessions Page 2</h1>
  ${username ? `<p><b>Name:</b> ${escapeHtml(username)}</p>` : `<p><b>Name:</b> You do not have a name set</p>`}
  <br/><br/>
  <a href="./node-sessions-1.js">Session Page 1</a><br/>
  <a href="/python-cgiform.html">Back to Form</a><br/>
  <form style="margin-top:30px" action="./node-destroy-session.js" method="get">
    <button type="submit">Destroy Session</button>
  </form>
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

function escapeHtml(s) {
  return String(s ?? "").replace(/&/g, "&amp;")
    .replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

