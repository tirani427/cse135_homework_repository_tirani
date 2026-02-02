<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json; charset=utf-8");

$date = date("r");

$address = $_SERVER["REMOTE_ADDR"] ?? "unknown";

$message = [
	"title" => "Hello, PHP!",
	"heading" => "Hello, PHP!",
	"message" => "This message was generated with PHP programming language, using json.",
	"time" => $date,
	"IP" => $address
];

echo json_encode($message, JSON_PRETTY_PRINT);
