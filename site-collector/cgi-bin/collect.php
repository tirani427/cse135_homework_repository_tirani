<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
    http_response_code(204);
    exit();
}

$raw = file_get_contents("php://input");
if($raw === false) {http_response_code(400); exit();}

$logFile = "/tmp/collector_events.log";
file_put_contents($logFile, $raw . "\n", FILE_APPEND);
http_response_code(204);