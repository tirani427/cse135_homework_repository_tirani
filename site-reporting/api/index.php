<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit(); }

session_start();

require_once 'validate.php';
require_once 'sessionize.php';
require_once 'authenticate.php';

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

function requireAuth(): void {
    if(empty($_SESSION['user'])){
        json_response(['success' => false, 'error' => 'Authentification required']);
    }
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
 * /api/login
 * /api/dashboard
 * /api/performance
 * /api/pageviews
 * /api/errors
 * /api/logout
 */

$uriPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$uriPath = preg_replace('#^/api/index\.php#', '/api', $uriPath);
$parts = array_values(array_filter(explode("/", $uriPath)));

if (count($parts) < 2 || $parts[0] !== "api") {
    json_response(["error" => "not found"], 404);
}

$route = $parts[1];
$method = $_SERVER["REQUEST_METHOD"];

$id = null;
if(isset($parts[2])){
    $id = validateId($parts[2]);
    if($id === null){
        json_response(["error" => "invalid id"], 404);
    }
}

$method = $_SERVER["REQUEST_METHOD"];

// ------------------------------------------------
// AUTHENTIFICATION
// ------------------------------------------------

if($method === 'POST' && $route === 'login'){
    // handle login...
    $body = read_json_body();
    if($body === null){
        json_response(['success' => false, 'error' => "Invalid JSON body"], 400);
    }

    $email = $body['email'] ?? '';
    $password = $body['password'] ?? '';

    $user = authenticate($pdo, $email, $password);
    if(!$user){
        json_response(['success' => false, 'error' => 'Invalid credentials'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    json_response(['success' => true, 'data' => $user]);
}

if($method === 'POST' && $route === 'logout'){
    //handle logout
    session_destroy();
    json_response(['success' => true], 200);
}

// -----------------------------------------------
// DASHBOARD
// -----------------------------------------------

if($method === 'GET' && $route === 'dashboard'){
    //handle dashboard...
    require_auth();

    $start = $_GET["start"] ?? date("Y-m-01 00:00:00");
    $end = $_GET["end"] ?? date("Y-m-d H:i:s");

    $stmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM pageviews
        WHERE server_timestamp BETWEEN ? AND ?
        AND event_name = "pageview") AS total_pageviews,
        (SELECT COUNT(DISTINCT session_id) FROM pageviews
        WHERE server_timestamp BETWEEN ? AND ?) AS total_sessions,
        (SELECT ROUND(AVG(load_time)) FROM performance
        WHERE server_timestamp BETWEEN ? AND ?) AS avg_load_time_ms,
        (SELECT COUNT(*) FROM errors
        WHERE server_timestamp BETWEEN ? AND ?) AS total_errors
    ');
    $stmt->execute([$start, $end, $start, $end, $start, $end, $start, $end]);
    $row = $stmt->fetch();

    json_response(["success" => true, "data" => $row], 200);
}

if($method === "GET" && $route === 'events'){
    if($id !== null){
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
        $stmt->execute([":id" => $id]);
        $row = $stmt->fetch();
        if (!$row){
            json_response(["error" => "Not found"], 404);
        }
        json_response($row, 200); 
    }

    $validatedParams = validateQueryParams($_GET);
    if($validatedParams === null){
        json_response(["error" => "invalid query parameters"], 400);
    }

    $session_id = $validatedParams['session_id'];
    $eventName = $validatedParams['event_name'];
    $limit = $validatedParams['limit'];
    $offset = $validatedParams['offset'];

    $where = [];
    $params = [":limit" => $limit, ":offset" => $offset];

    if ($session_id !== null && $session_id !== "") { 
        $where[] = "session_id = :session_id"; 
        $params[":session_id"] = $session_id; 
    }
   
    if ($eventName !== null && $eventName !== "") { 
        $where[] = "event_name = :event_name"; 
        $params[":event_name"] = $eventName; 
    }

    $sql = "SELECT * FROM events";
    if (count($where) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY server_timestamp DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, in_array($k, [":limit", ":offset"], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    json_response($rows, 200);
}

// ---------- POST ----------
if ($method === "POST" && $route === 'events') {
    if ($id !== null){
        json_response(["error" => "Do not include id on POST"], 400);
    }

    $body = read_json_body();
    if ($body === null) {
        json_response(["error" => "Invalid JSON body"], 400);
    }

    $validated = validateBeacon($body);
    if($validated === null){
        json_response(["error" => "Invalid beacon"], 400);
    }

    file_put_contents(
        __DIR__ . "/../logs/beacons.log",
        json_encode($validated, JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );

    if ($validated["type"] === "pageview") {
        sessionize($pdo, $validated);
    }

    $session_id = isset($validated["sessionId"]) ? substr((string)$validated["sessionId"], 0, 64) : null;
    if (!$session_id) {
        json_response(["error" => "sessionId required"], 400);
    }

    $eventName = isset($validated["type"]) ? substr((string)$validated["type"], 0, 32) : null;
    $pageUrl = isset($validated["url"]) ? (string)$validated["url"] : null;
    $server_timestamp= date("Y-m-d H:i:s");

    $payload = [
        "userAgent" => $validated["userAgent"],
        "viewportHeight" => $validated["viewportHeight"],
        "viewportWidth" => $validated["viewportWidth"],
        "referrer" => $validated["referrer"],
        "clientTimestamp" => $validated["timestamp"],
        "payload" => $validated["payload"]
    ];

   
    $stmt = $pdo->prepare("
        INSERT INTO events (session_id, event_name, event_category, event_data, url, server_timestamp)
        VALUES (:session_id, :event_name, :event_category, CAST(:event_data AS JSON), :url, :server_timestamp)
    ");

    $stmt->execute([
        ":session_id" => $session_id,
        ":event_name" => $eventName,
        ":event_category" => null,
        ":event_data" => json_encode($eventData, JSON_UNESCAPED_SLASHES),
        ":url" => $pageUrl,
        ":server_timestamp" => $server_timestamp
    ]);

    json_response(["id" => (int)$pdo->lastInsertId()], 201);
}

if ($method === "PUT" && $route === 'events') {
    if ($id === null) {
        json_response(["error" => "PUT requires id"], 400);
    }
    
    $body = read_json_body();
    $validated = validateEventUpdate($body);

    if($body === null){
        json_response(["error" => "Invalid JSON body"], 400);
    }

    if($validated === null){
        json_response(["error" => "Invalid update body"], 400);
    }

    // Allow updating any fields, but keep it simple:
    $fields = [];
    $params = [":id" => $id];

    if (isset($validated["session_id"])) { 
        $fields[] = "session_id = :session_id"; 
        $params[":session_id"] = substr((string)$validated["session_id"], 0, 64); 
    }
    if (isset($validated["event_name"])) { 
        $fields[] = "event_name = :event_name"; 
        $params[":event_name"] = substr((string)$validated["event_name"], 0, 32); 
    }
    if (isset($validated["event_category"])) { 
        $fields[] = "event_category = :event_category"; 
        $params[":event_category"] = substr((string)$validated["event_category"], 0, 32); 
    }
    if (isset($validated["event_data"])) { 
        $json = json_encode($validated["event_data"], JSON_UNESCAPED_SLASHES);
        $fields[] = "event_data = CAST(:event_data AS JSON)"; 
        $params[":event_data"] = $json; 
    }
    if (array_key_exists("url", $validated)) { 
        $fields[] = "url = :url"; 
        $params[":url"] = $validated["url"]; 
    }
    if (array_key_exists("server_timestamp", $validated)) { 
        $fields[] = "server_timestamp = :server_timestamp"; 
        $params[":server_timestamp"] = $validated["server_timestamp"] === null ? null : (int)$validated["server_timestamp"]; 
    }

    if (count($fields) === 0) {
        json_response(["error" => "No updatable fields provided"], 400);
    }

    $sql = "UPDATE events SET " . implode(", ", $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(["updated" => $stmt->rowCount()], 200);
}

if ($method === "DELETE" && $route === 'events') {
    if ($id === null) {
        json_response(["error" => "DELETE requires id"], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute([":id" => $id]);

    json_response(["deleted" => $stmt->rowCount()], 200);
}


if($method === 'GET' && $route === 'pageviews'){
    //handle pageviews
    json_response(["message" => "pageviews route not yet implemented"], 501);
}

if($method === 'GET' && $route === 'performance'){
    //handle performance
    json_response(["message" => "performance route not yet implemented"], 501);
}

if($method === 'GET' && $route === 'errors'){
    //handle errors
    json_response(["message" => "errors route not yet implemented"], 501);
}

if($method === 'GET' && $route === 'sessions'){
    //handle sessions
    json_response(["message" => "sessions route not yet implemented"], 501);
}



json_response(["error" => "Method not allowed"], 405);
