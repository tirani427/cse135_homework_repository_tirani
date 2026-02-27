#!/usr/bin/env node

const date = new Date().toString();
const ip = process.env.REMOTE_ADDR || "unknown";

const message = {
  title: "Hello, Node!",
  heading: "Hello, Node!",
  message: "This page was generated with the Node.js programming language",
  time: date,
  IP: ip
};

console.log("Cache-Control: no-cache");
console.log("Content-Type: application/json; charset=utf-8\n");
console.log(JSON.stringify(message, null, 2));
