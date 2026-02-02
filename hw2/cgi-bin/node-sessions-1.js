#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const SESSION_DIR = "/tmp";
const COOKIE_NAME = "CGISESSID";

const method = (process.env.REQUEST_METHOD || "GET").toUpperCase();
const query = process.env.QUERY_STRING || "";
const contentType = process.env.CONTENT_TYPE || "";
const contentLen = parseInt(process.env.CONTENT_LENGTH || "0", 10) || 0;

const cookies = parseCookies(process.env.HTTP_COOKIE || "");
let sid = cookies[COOKIE_NAME] || null;

if (!sid) sid = crypto.randomBytes(16).toString("hex");

const sessionPath = path.join(SESSION_DIR, `nodecgi_session_${sid}.json`);
let session = loadSession(sessionPath);

// Read username from GET or POST (x-www-form-urlencoded)
readBody(contentLen).then((body) => {
  const params =
    method === "POST"
      ? (contentType.startsWith("application/x-www-form-urlencoded")
          ? parseUrlEncoded(body)
          : {})
      : parseUrlEncoded(query);

  const incoming = first(params, "username");
  if (incoming && incoming.trim()) {
    session.username = incoming.trim();
    saveSession(sessionPath, session);
  }

  // Output headers
  console.log("Cache-Control: no-cache");
  console.log("Content-Type: text/html; charset=utf-8");
  console.log(`Set-Cookie: ${COOKIE_NAME}=${sid}; Path=/; HttpOnly`);
  console.log("");

  // HTML
  console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Node Sessions</title></head>
<body>
  <h1>Node Sessions Page 1</h1>
  ${session.username ? `<p><b>Name:</b> ${escapeHtml(session.username)}</p>` : `<p><b>Name:</b> You do not have a name set</p>`}
  <br/><br/>
  <a href="./node-sessions-2.js">Session Page 2</a><br/>
  <a href="/python-cgiform.html">Back to Form</a><br/>
  <form style="margin-top:30px" action="./node-destroy-session.js" method="get">
    <button type="submit">Destroy Session</button>
  </form>
</body>
</html>`);
}).catch((e) => {
  console.log("Status: 500 Internal Server Error");
  console.log("Content-Type: text/plain; charset=utf-8\n");
  console.log(String(e && e.stack ? e.stack : e));
});

function loadSession(file) {
  try {
    if (!fs.existsSync(file)) return {};
    return JSON.parse(fs.readFileSync(file, "utf8"));
  } catch {
    return {};
  }
}
function saveSession(file, obj) {
  fs.writeFileSync(file, JSON.stringify(obj));
}

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

function parseUrlEncoded(s) {
  const out = {};
  if (!s) return out;
  for (const pair of s.split("&")) {
    if (!pair) continue;
    const [kRaw, vRaw = ""] = pair.split("=");
    const k = decodeURIComponent((kRaw || "").replace(/\+/g, " "));
    const v = decodeURIComponent((vRaw || "").replace(/\+/g, " "));
    (out[k] ||= []).push(v);
  }
  return out;
}

function first(params, key) {
  const v = params[key];
  if (!v || v.length === 0) return "";
  return String(v[0]);
}

function readBody(maxBytes) {
  return new Promise((resolve) => {
    if (!maxBytes) return resolve("");
    let data = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      data += chunk;
      if (data.length >= maxBytes) data = data.slice(0, maxBytes);
    });
    process.stdin.on("end", () => resolve(data));
  });
}

function escapeHtml(s) {
  return String(s ?? "").replace(/&/g, "&amp;")
    .replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

