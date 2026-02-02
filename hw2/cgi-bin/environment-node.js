#!/usr/bin/env node

console.log("Cache-Control: no-cache");
console.log("Content-Type: text/html; charset=utf-8\n");

const keys = Object.keys(process.env).sort();

console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Environment Variables</title></head>
<body>
  <h1 style="text-align:center;">Environment Variables</h1>
  <hr/>
`);

for (const k of keys) {
  const v = process.env[k] ?? "";
  console.log(`<b>${escapeHtml(k)}:</b> ${escapeHtml(String(v))}<br/>`);
}

console.log(`</body></html>`);

function escapeHtml(s) {
  return s.replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
}
