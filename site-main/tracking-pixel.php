<?php
header('Cache-Control:no-store, no-cache, must-revalidate');
header('Pragma:no-cache');
header('Expires:0');
header('Content-Type:image/gif');

echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

$data = [
    'timestamp' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    'page' => $_GET['page'] ?? '',
    'type' => $_GET['t'] ?? 'pageview',
    'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
];

$logFile = __DIR__ . '/pixel-hits.jsonl';
file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);