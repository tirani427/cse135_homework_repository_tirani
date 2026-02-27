#!/usr/bin/env node

const query = process.env.QUERY_STRING || "";
const params = parseUrlEncoded(query);

console.log("Cache-Control: no-cache");
console.log("Content-Type: text/html; charset=utf-8\n");

console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>GET Request Echo</title></head>
<body>
  <h1 style="text-align:center;">GET Request Echo</h1>
  <hr/>
  <b>Query String:</b> ${escapeHtml(query)}<br/>
  <br/>
`);

for (const [k, vals] of Object.entries(params)) {
  for (const v of vals) {
    console.log(`${escapeHtml(k)} = ${escapeHtml(v)}<br/>`);
  }
}

console.log(`</body></html>`);

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

function escapeHtml(s) {
  return String(s).replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
}

