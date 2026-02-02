#!/usr/bin/env node

const len = parseInt(process.env.CONTENT_LENGTH || "0", 10) || 0;

readStdin(len).then((body) => {
  const params = parseUrlEncoded(body);

  console.log("Cache-Control: no-cache");
  console.log("Content-Type: text/html; charset=utf-8\n");

  console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>POST Request Echo</title></head>
<body>
  <h1 style="text-align:center;">POST Request Echo</h1>
  <hr/>
  <b>Message Body:</b><br/>
  <ul>
`);

  for (const [k, vals] of Object.entries(params)) {
    for (const v of vals) {
      console.log(`<li>${escapeHtml(k)} = ${escapeHtml(v)}</li>`);
    }
  }

  console.log(`</ul>
</body></html>`);
}).catch((e) => {
  console.log("Status: 500 Internal Server Error");
  console.log("Content-Type: text/plain; charset=utf-8\n");
  console.log(String(e && e.stack ? e.stack : e));
});

function readStdin(maxBytes) {
  return new Promise((resolve) => {
    let data = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      data += chunk;
      if (data.length >= maxBytes) {
        data = data.slice(0, maxBytes);
      }
    });
    process.stdin.on("end", () => resolve(data));
  });
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

function escapeHtml(s) {
  return String(s).replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
}

