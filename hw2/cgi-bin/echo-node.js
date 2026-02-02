#!/usr/bin/env node

(async function main() {
  const method = (process.env.REQUEST_METHOD || "GET").toUpperCase();
  const queryString = process.env.QUERY_STRING || "";
  const contentType = process.env.CONTENT_TYPE || "";
  const ip = process.env.REMOTE_ADDR || "unknown";
  const userAgent = process.env.HTTP_USER_AGENT || "unknown";
  const host = process.env.SERVER_NAME || "unknown";
  const time = new Date().toISOString();

  const bodyLen = parseInt(process.env.CONTENT_LENGTH || "0", 10) || 0;
  const rawBody = bodyLen > 0 ? await readStdin(bodyLen) : "";

  let params = {};
  if (method === "GET") {
    params = parseUrlEncoded(queryString);
  } else if (contentType.startsWith("application/json")) {
    try {
      params = rawBody ? JSON.parse(rawBody) : {};
    } catch {
      params = { _json_error: "invalid json", _raw: rawBody };
    }
  } else {
    params = parseUrlEncoded(rawBody);
  }

  const response = {
    endpoint: "echo-node",
    method,
    content_type: contentType,
    query_string: queryString,
    params,
    raw_body: rawBody,
    meta: { hostname: host, time, user_agent: userAgent, ip }
  };

  console.log("Cache-Control: no-cache");

  if (contentType.startsWith("application/json")) {
    console.log("Content-Type: application/json; charset=utf-8\n");
    console.log(JSON.stringify(response, null, 2));
  } else {
    // simple HTML echo
    const name = first(params, "name");
    const message = first(params, "message");

    console.log("Content-Type: text/html; charset=utf-8\n");
    console.log(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Echo (Node)</title></head>
<body>
  <h2>Echo (x-www-form-urlencoded)</h2>
  <ul>
    <li>name = ${escapeHtml(name)}</li>
    <li>message = ${escapeHtml(message)}</li>
  </ul>

  <p><b>Method:</b> ${escapeHtml(method)}</p>
  <p><b>Content-Type:</b> ${escapeHtml(contentType)}</p>
  <p><b>Query-String:</b> ${escapeHtml(queryString)}</p>
  <p><b>IP:</b> ${escapeHtml(ip)}</p>
  <p><b>User-Agent:</b> ${escapeHtml(userAgent)}</p>
  <p><b>Time:</b> ${escapeHtml(time)}</p>
</body>
</html>`);
  }
})().catch((e) => {
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
      if (data.length >= maxBytes) data = data.slice(0, maxBytes);
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

function first(params, key) {
  const v = params[key];
  if (!v || v.length === 0) return "";
  return String(v[0]);
}

function escapeHtml(s) {
  return String(s ?? "").replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
}

