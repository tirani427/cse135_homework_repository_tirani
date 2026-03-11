<?php
$allowedOrigins = [
    'https://test.cse135tirani.site',
    'https://cse135tirani.site',
    'https://reporting.cse135tirani.site'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit();
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed\n";
    exit();
}

$raw = file_get_contents("php://input");
if ($raw === false || trim($raw) === "") {
    http_response_code(400);
    echo "Empty body\n";
    exit();
}

$data = json_decode($raw, true);
if(!is_array($data) || empty($data['url'])){
    http_response_code(204);
    exit();
}

$serverTimestamp = date('Y-m-d H:i:s');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

$sid = isset($data["sid"])
    ? substr((string)$data["sid"], 0, 36)
    : (isset($data["session"]) ? substr((string)$data["session"], 0, 36) : "missing");
$event_type = isset($data["type"]) ? substr((string)$data["type"], 0, 32) : "unknown";
if ($event_type === "") {
    $event_type = "unknown";
}

$page_url = isset($data["url"]) ? substr((string)$data["url"], 0, 2048) : null;
$client_ts = isset($data["ts"]) ? (int)$data["ts"] : null;

$cfg = require "/etc/cse135/collector_db.php";

try{
    $pdo = new PDO(
        $cfg["dsn"],
        $cfg["user"],
        $cfg["pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if ($event_type === "activity_batch" && isset($data["events"]) && is_array($data["events"])) {
        $stmt = $pdo->prepare("
            INSERT INTO events (
                session_id,
                event_name,
                event_category,
                event_data,
                url,
                server_timestamp
            )
            VALUES (
                :session_id,
                :event_name,
                :event_category,
                CAST(:event_data AS JSON),
                :url,
                :server_timestamp
            )
        ");

        foreach ($data["events"] as $event) {
            $rowType = "activity";
            if (is_array($event) && isset($event["kind"])) {
                $rowType = "activity_" . substr((string)$event["kind"], 0, 24);
            }

            $rowTs = $client_ts;
            if (is_array($event) && isset($event["ts"])) {
                $rowTs = (int)$event["ts"];
            }

            $eventData = [
                "client_ts" => $rowTs,
                "event" => $event
            ];

            $stmt->execute([
                ":session_id" => substr((string)$sid, 0, 36),
                ":event_name" => substr((string)$rowType, 0, 128),
                ":event_category" => "activity",
                ":event_data" => json_encode($eventData, JSON_UNESCAPED_SLASHES),
                ":url" => $page_url,
                ":server_timestamp" => $serverTimestamp
            ]);
        }
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO events (
                session_id,
                event_name,
                event_category,
                event_data,
                url,
                server_timestamp
            )
            VALUES (
                :session_id,
                :event_name,
                :event_category,
                CAST(:event_data AS JSON),
                :url,
                :server_timestamp
            )
        ");

        $eventData = [
            "client_ts" => $client_ts,
            "raw" => $data
        ];

        $stmt->execute([
            ":session_id" => substr((string)$sid, 0, 36),
            ":event_name" => substr((string)$event_type, 0, 128),
            ":event_category" => null,
            ":event_data" => json_encode($eventData, JSON_UNESCAPED_SLASHES),
            ":url" => $page_url,
            ":server_timestamp" => $serverTimestamp
        ]);
    }
    if ($event_type === "pageview") {
        $tech = isset($data["technographics"]) && is_array($data["technographics"]) ? $data["technographics"] : [];

        $stmtPv = $pdo->prepare("
            INSERT INTO pageviews (
                url,
                type,
                user_agent,
                viewport_width,
                viewport_height,
                referrer,
                client_timestamp,
                server_timestamp,
                client_ip,
                session_id,
                payload
            ) VALUES (
                :url,
                :type,
                :user_agent,
                :viewport_width,
                :viewport_height,
                :referrer,
                :client_timestamp,
                :server_timestamp,
                :client_ip,
                :session_id,
                CAST(:payload AS JSON)
            )
        ");

        $stmtPv->execute([
            ":url" => $page_url,
            ":type" => $event_type,
            ":user_agent" => isset($tech["userAgent"]) ? substr((string)$tech["userAgent"], 0, 512) : null,
            ":viewport_width" => isset($tech["viewportWidth"]) ? (int)$tech["viewportWidth"] : null,
            ":viewport_height" => isset($tech["viewportHeight"]) ? (int)$tech["viewportHeight"] : null,
            ":referrer" => isset($data["referrer"]) ? substr((string)$data["referrer"], 0, 2048) : null,
            ":client_timestamp" => $client_ts,
            ":server_timestamp" => $serverTimestamp,
            ":client_ip" => substr((string)$clientIp, 0, 45),
            ":session_id" => substr((string)$sid, 0, 36),
            ":payload" => json_encode($data, JSON_UNESCAPED_SLASHES)
        ]);
    }
    if ($event_type === "error") {
        $payload = (isset($data["error"]) && is_array($data["error"])) ? $data["error"] : $data;

        $stmtErr = $pdo->prepare("
            INSERT INTO errors (
                session_id,
                error_message,
                error_source,
                error_line,
                error_column,
                stack_trace,
                url,
                user_agent,
                server_timestamp
            ) VALUES (
                :session_id,
                :error_message,
                :error_source,
                :error_line,
                :error_column,
                :stack_trace,
                :url,
                :user_agent,
                :server_timestamp
            )
        ");

        $stmtErr->execute([
            ":session_id" => substr((string)$sid, 0, 36),
            ":error_message" => isset($payload["message"]) ? substr((string)$payload["message"], 0, 1024) : "Unknown error",
            ":error_source" => isset($payload["source"]) ? substr((string)$payload["source"], 0, 2048) : null,
            ":error_line" => isset($payload["line"]) ? (int)$payload["line"] : null,
            ":error_column" => isset($payload["column"]) ? (int)$payload["column"] : null,
            ":stack_trace" => isset($payload["stack"]) ? (string)$payload["stack"] : null,
            ":url" => $page_url,
            ":user_agent" => isset($data["userAgent"]) ? substr((string)$data["userAgent"], 0, 512) : null,
            ":server_timestamp" => $serverTimestamp
        ]);
    }
    if ($event_type === "performance") {
        $payload = $data["payload"] ?? $data;

        $stmtPerf = $pdo->prepare("
            INSERT INTO performance (
                session_id,
                url,
                user_agent,
                load_time,
                ttfb,
                fcp,
                lcp,
                cls,
                inp,
                server_timestamp
            ) VALUES (
                :session_id,
                :url,
                :user_agent,
                :load_time,
                :ttfb,
                :fcp,
                :lcp,
                :cls,
                :inp,
                :server_timestamp
            )
        ");

        $stmtPerf->execute([
            ":session_id" => substr((string)$sid, 0, 36),
            ":url" => $page_url,
            ":user_agent" => isset($tech["userAgent"]) ? substr((string)$tech["userAgent"], 0, 512) : null,
            ":load_time" => isset($pageLoad["totalLoadMS"]) ? (float)$pageLoad["totalLoadMS"] : null,
            ":ttfb" => isset($timing["ttfb"]) ? (float)$timing["ttfb"] : null,
            ":fcp" => null,
            ":lcp" => isset($vitals["lcp"]) ? (float)$vitals["lcp"] : null,
            ":cls" => isset($vitals["cls"]) ? (float)$vitals["cls"] : null,
            ":inp" => isset($vitals["inp"]) ? (float)$vitals["inp"] : null,
            ":server_timestamp" => $serverTimestamp
        ]);
    }
    http_response_code(204);
    exit();
} catch (Exception $e){
    error_log("collector insert failed: " . $e->getMessage());
    http_response_code(500);
    echo "Server error\n";
    exit();
}