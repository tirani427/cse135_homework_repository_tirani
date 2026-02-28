<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain; charset=utf-8");


if($_SERVER["REQUEST_METHOD"] === "OPTIONS"){
    http_response_code(204);
    exit();
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    http_response_code(405);
    echo "Method Not Allowed\n";
    exit();
}

$raw = file_get_contents("php://input");
if($raw === false || trim($raw) === ""){
    http_response_code(400);
    echo "Empty body\n";
    exit();
}
$data = json_decode($raw, true);
if(!is_array($data)){
    http_response_code(400);
    echo "Invalid JSON\n";
    exit();
}
$sid = isset($data["sid"]) ? substr((string)$data["sid"],0,64) : "missing";
$event_type = isset($data["type"]) ? substr((string)$data["type"], 0, 32) : "unknown";
if ($event_type === null || $event_type === "") {
  $event_type = "unknown";
}

$page_url = isset($data["url"]) ? (string)$data["url"] : null;
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

    if($event_type === "activity_batch" && isset($data["events"]) && is_array($data["events"])){
        $stmt = $pdo->prepare("
        INSERT INTO events (sid, event_type, page_url, client_ts, payload) 
        VALUES (:sid, :event_type, :page_url, :client_ts, CAST(:payload AS JSON))
        ");

        foreach($data["events"] as $event){
            $rowType = "activity";
            if(is_array($event) && isset($event["kind"])){
                $rowType = "activity_" . substr((string)$event["kind"],0,24);
            }
            $rowTs = $client_ts;
            if(is_array($event) && isset($event["ts"])){
                $rowTs = (int)$event["ts"];
            }

            $stmt->execute([
                ":sid" => $sid,
                ":event_type" => $rowType,
                ":page_url" => $page_url,
                ":client_ts" => $rowTs,
                ":payload" => json_encode($event, JSON_UNESCAPED_SLASHES)
            ]);
        }
    } else {
        $stmt = $pdo->prepare("
        INSERT INTO events (sid, event_type, page_url, client_ts, payload)
        VALUES (:sid, :event_type, :page_url, :client_ts, CAST(:payload AS JSON))
        ");

        $stmt->execute([
            ":sid" => $sid,
            ":event_type" => $event_type,
            ":page_url" => $page_url,
            ":client_ts" => $client_ts,
            ":payload" => json_encode($data, JSON_UNESCAPED_SLASHES)
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