<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit(); }

$cfg = require "/etc/cse135/collector_db.php";

function json_response($data, int $code = 200){
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit();
}

function read_json_body(){
    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === "") return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

try {
  $pdo = new PDO(
    $cfg["dsn"], $cfg["user"], $cfg["pass"],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Exception $e) {
  json_response(["error" => "DB connect failed"], 500);
}

/**
 * Routing:
 * /api/events
 * /api/events/{id}
 */

$uriPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$uriPath = preg_replace('#^/api/index\.php#', '/api', $uriPath);

$parts = array_values(array_filter(explode("/", $uriPath)));

if(count($parts) < 2 || $parts[0] !== "api" || $parts[1] !== "events"){
    json_response(["error" => "not found"], 404);
}

$id = null;
if(isset($parts[2])){
    if(!ctype_digit($parts[2])) json_response(["error" => "invalid id"], 404);
    $id = (int)$parts[2];
}

$method = $_SERVER["REQUEST_METHOD"];

if($method === "GET"){
    if($id !== null){
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute([":id" => $id]);
        $row = $stmt->fetch();
        if (!$row) json_response(["error" => "Not found"], 404);
        json_response($row, 200); 
    }

    $sid = isset($_GET["sid"]) ? substr((string)$_GET["sid"], 0, 64) : null;
    $type = isset($_GET["type"]) ? substr((string)$_GET["type"], 0, 32) : null;
    $limit = isset($_GET["limit"]) ? max(1, min(500, (int)$_GET["limit"])) : 100;
    $offset = isset($_GET["offset"]) ? max(0, (int)$_GET["offset"]) : 0;

    $where = [];
    $params = [":limit" => $limit, ":offset" => $offset];

    if ($sid !== null && $sid !== "") { 
        $where[] = "sid = :sid"; 
        $params[":sid"] = $sid; 
    }
   
    if ($type !== null && $type !== "") { 
        $where[] = "event_type = :type"; 
        $params[":type"] = $type; 
    }

    $sql = "SELECT * FROM events";
    if (count($where) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, in_array($k, [":limit", ":offset"], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    json_response($rows, 200);
}

// ---------- POST ----------
if ($method === "POST") {
    if ($id !== null) json_response(["error" => "Do not include id on POST"], 400);
    $body = read_json_body();
    if ($body === null) json_response(["error" => "Invalid JSON body"], 400);

    $sid = isset($body["sid"]) ? substr((string)$body["sid"], 0, 64) : null;
    $eventType = isset($body["event_type"]) ? substr((string)$body["event_type"], 0, 32) : null;
    $pageUrl = isset($body["page_url"]) ? (string)$body["page_url"] : null;
    $clientTs = isset($body["client_ts"]) ? (int)$body["client_ts"] : null;
    $payload = isset($body["payload"]) ? $body["payload"] : $body;

    if (!$sid || !$eventType) json_response(["error" => "sid and event_type required"], 400);

    $stmt = $pdo->prepare("
        INSERT INTO events (sid, event_type, page_url, client_ts, payload)
        VALUES (:sid, :event_type, :page_url, :client_ts, CAST(:payload AS JSON))
    ");
    $stmt->execute([
        ":sid" => $sid,
        ":event_type" => $eventType,
        ":page_url" => $pageUrl,
        ":client_ts" => $clientTs,
        ":payload" => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);

    json_response(["id" => (int)$pdo->lastInsertId()], 201);
}

if ($method === "PUT") {
    if ($id === null) json_response(["error" => "PUT requires id"], 400);
    $body = read_json_body();
    if ($body === null) json_response(["error" => "Invalid JSON body"], 400);

    // Allow updating any fields, but keep it simple:
    $fields = [];
    $params = [":id" => $id];

    if (isset($body["sid"])) { $fields[] = "sid = :sid"; $params[":sid"] = substr((string)$body["sid"], 0, 64); }
    if (isset($body["event_type"])) { $fields[] = "event_type = :event_type"; $params[":event_type"] = substr((string)$body["event_type"], 0, 32); }
    if (array_key_exists("page_url", $body)) { $fields[] = "page_url = :page_url"; $params[":page_url"] = $body["page_url"]; }
    if (array_key_exists("client_ts", $body)) { $fields[] = "client_ts = :client_ts"; $params[":client_ts"] = $body["client_ts"] === null ? null : (int)$body["client_ts"]; }
    if (isset($body["payload"])) { $fields[] = "payload = CAST(:payload AS JSON)"; $params[":payload"] = json_encode($body["payload"], JSON_UNESCAPED_SLASHES); }

    if (count($fields) === 0) json_response(["error" => "No updatable fields provided"], 400);

    $sql = "UPDATE events SET " . implode(", ", $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(["updated" => $stmt->rowCount()], 200);
}

if ($method === "DELETE") {
    if ($id === null) json_response(["error" => "DELETE requires id"], 400);
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute([":id" => $id]);
    json_response(["deleted" => $stmt->rowCount()], 200);
}

json_response(["error" => "Method not allowed"], 405);
