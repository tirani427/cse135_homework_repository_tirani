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

function get_permissions(){
    $raw = $_SESSION['user']['permissions'] ?? '';
    if(!$raw){
        return [];
    }

    return array_map('trim', explode(',', $raw));
}

function requireAuth(): void {
    if(empty($_SESSION['user'])){
        json_response(['success' => false, 'error' => 'Authentification required'], 401);
    }
}

function requireRole(array $allowedRoles): void {
    requireAuth();

    $role = $_SESSION['user']['role'] ?? null;
    if(!in_array($role, $allowedRoles, true)){
        json_response([
            'success' => false,
            'error' => 'Insufficient permissions'
        ], 403);
    }
}

function requirePermissions(array $allowedRoles, array $required){
    requireAuth();

    $permission = get_permissions();
    $role = $_SESSION['user']['role'] ?? null;

    if($role === 'super admin'){
        return;
    }

    requireRole($allowedRoles);

    $hasPermission = false;
    foreach($required as $perm){
        if(in_array($perm, $permisions, true)){
            $hasPermission = true;
            break;
        }
    }

    if(!$hasPermission){
        json_response([
            'success' => false,
            'error' => 'Insufficient permissions'
        ], 403);
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
    $_SESSION = [];
    session_destroy();
    json_response(['success' => true], 200);
}

// -----------------------------------------------
// DASHBOARD
// -----------------------------------------------

if($method === 'GET' && $route === 'dashboard'){
    requireAuth();

    requirePermissions(['super admin', 'analyst', 'viewer'], ['reports']);

    $start = ($_GET["start"] ?? date("Y-m-01")) . " 00:00:00";
    $end = ($_GET["end"] ?? date("Y-m-d")) . " 23:59:59";

    $stmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM pageviews
         WHERE server_timestamp BETWEEN ? AND ?
           AND type = "pageview") AS total_pageviews,
        (SELECT COUNT(DISTINCT session_id) FROM pageviews
         WHERE server_timestamp BETWEEN ? AND ?
           AND type = "pageview") AS total_sessions,
        (SELECT ROUND(AVG(load_time)) FROM performance
         WHERE server_timestamp BETWEEN ? AND ?) AS avg_load_time_ms,
        (SELECT COUNT(*) FROM errors
         WHERE server_timestamp BETWEEN ? AND ?) AS total_errors
    ');
    $stmt->execute([$start, $end, $start, $end, $start, $end, $start, $end]);
    $row = $stmt->fetch();

    json_response(["success" => true, "data" => $row], 200);
}

// ------------------------------------------------
// EVENTS 
// ------------------------------------------------

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

// ------------------------------------------------
// PAGEVIEWS 
// ------------------------------------------------

if ($method === 'GET' && $route === 'pageviews') {
    requireAuth();

    $start = ($_GET['start'] ?? date('Y-m-01')) . ' 00:00:00';
    $end = ($_GET['end'] ?? date('Y-m-d')) . ' 23:59:59';

    $byDayStmt = $pdo->prepare("
        SELECT DATE(server_timestamp) AS day, COUNT(*) AS views
        FROM pageviews
        WHERE server_timestamp BETWEEN :start AND :end
          AND type = 'pageview'
        GROUP BY DATE(server_timestamp)
        ORDER BY day
    ");
    $byDayStmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);
    $byDay = $byDayStmt->fetchAll();

    $topPagesStmt = $pdo->prepare("
        SELECT url, COUNT(*) AS views
        FROM pageviews
        WHERE server_timestamp BETWEEN :start AND :end
          AND type = 'pageview'
        GROUP BY url
        ORDER BY views DESC
        LIMIT 20
    ");
    $topPagesStmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);
    $topPages = $topPagesStmt->fetchAll();

    json_response([
        'success' => true,
        'data' => [
            'byDay' => $byDay,
            'topPages' => $topPages
        ]
    ], 200);
}

// ------------------------------------------------
// PERFORMANCE 
// ------------------------------------------------

if($method === 'GET' && $route === 'performance'){
    //handle performance
    requireAuth();

    $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
    $end = $_GET['end'] ?? date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT
            url,
            COUNT(*) AS samples,
            ROUND(AVG(load_time), 2) AS avg_load_time,
            ROUND(AVG(ttfb),2) AS avg_ttfb,
            ROUND(AVG(lcp), 2) AS avg_lcp,
            ROUND(AVG(cls), 4) AS avg_cls
        FROM performance
        WHERE server_timestamp >= :start
        AND server_timestamp < DATE_ADD(:end, INTERVAL 1 DAY)
        GROUP BY url
        ORDER BY avg_load_time DESC
        LIMIT 20
    ");

    $stmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);


    json_response([
        'success' => true, 
        'data' =>[
            "byPage" => $stmt->fetchAll()
        ]
    ]);
}

// ------------------------------------------------
// ERRORS 
// ------------------------------------------------

if($method === 'GET' && $route === 'errors'){
    //handle errors
    requireAuth();

    $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
    $end = $_GET['end'] ?? date('Y-m-d H:i:s');

    $byMessage_stmt = $pdo->prepare("
        SELECT
            error_message,
            COUNT(*) AS occurrences,
            MAX(server_timestamp) AS last_seen
        FROM errors
        WHERE server_timestamp BETWEEN :start AND :end
        GROUP BY error_message
        ORDER BY occurrences DESC, last_seen DESC
        LIMIT 20
    ");
    $byMessage_stmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);

    $byMessage = $byMessage_stmt->fetchAll();

    $trend_stmt = $pdo->prepare("
        SELECT
            DATE(server_timestamp) AS day,
            COUNT(*) AS error_count
        FROM errors
        WHERE server_timestamp BETWEEN :start AND :end
        GROUP BY DATE(server_timestamp)
        ORDER BY day
    ");
    $trend_stmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);
    $trend = $trend_stmt->fetchAll();


    json_response([
        'success' => true,
        'data' => [
            'byMessage' => $byMessage,
            'trend' => $trend
        ]
    ], 200);
}

// ------------------------------------------------
// SESSIONS 
// ------------------------------------------------

if($method === 'GET' && $route === 'sessions'){
    //handle sessions
    requireAuth();

    $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
    $end = $_GET['end'] ?? date('Y-m-d H:i:s');

    $count_stmt = $pdo->prepare("
        SELECT
            DATE(start_time) AS day,
            COUNT(*) AS session_count
        FROM sessions
        WHERE start_time BETWEEN :start AND :end
        GROUP BY DATE(start_time)
        ORDER BY day
    ");
    $count_stmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);
    $counts = $count_stmt->fetchAll();

    $stats_stmt = $pdo->prepare("
        SELECT
            ROUND(AVG(duration_seconds), 2) AS avg_session_duration,
            ROUND(AVG(page_count), 2) AS avg_pages_per_session,
            ROUND(
                100.0 * SUM(CASE WHEN page_count = 1 THEN 1 ELSE 0 END) / COUNT(*),
                2
            ) AS bounce_rate
        FROM sessions
        WHERE start_time BETWEEN :start AND :end
    ");
    $stats_stmt->execute([
        ':start' => $start,
        ':end' => $end
    ]);
    $stats = $stats_stmt->fetch();


    json_response([
        'success' => true,
        'data' => [
            'countsByDay' => $counts,
            'stats' => $stats
        ]
    ]);
}

// ------------------------------------------------
// DASHBOARD
// ------------------------------------------------

if ($method === 'GET' && $route === 'dashboard') {
    requireAuth();

    $start = ($_GET['start'] ?? date('Y-m-01')) . ' 00:00:00';
    $end = ($_GET['end'] ?? date('Y-m-d')) . ' 23:59:59';

    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM pageviews
             WHERE server_timestamp BETWEEN ? AND ?
               AND type = 'pageview') AS total_pageviews,
            (SELECT COUNT(DISTINCT session_id) FROM pageviews
             WHERE server_timestamp BETWEEN ? AND ?) AS total_sessions,
            (SELECT ROUND(AVG(load_time)) FROM performance
             WHERE server_timestamp BETWEEN ? AND ?) AS avg_load_time_ms,
            (SELECT COUNT(*) FROM errors
             WHERE server_timestamp BETWEEN ? AND ?) AS total_errors
    ");

    $stmt->execute([$start, $end, $start, $end, $start, $end, $start, $end]);
    $row = $stmt->fetch();

    json_response([
        'success' => true,
        'data' => $row
    ], 200);
}

// ------------------------------------------------
// USERS
// ------------------------------------------------

if($method === 'GET' && $route === 'users'){
    requireRole(['super admin']);

    if($id !== null){
        $stmt = $pdo->prepare("
            SELECT id, email, display_name, role, created_at, last_login
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if(!$user){
            json_response([
                'success' => false,
                'error' => "User not found"
            ], 404);
        }
        json_response(['success' => true, 'data' => $user], 200);
    }

    $stmt = $pdo->query("
        SELECT id, email, display_name, role, created_at, last_login
        FROM users
        ORDER BY created_at DESC
    ");
    json_response([
        'success' => true,
        'data' => $stmt->fetchAll()
    ], 200);
}

if($method === 'POST' && $route === 'users'){
    requireRole(['owner', 'admin']);

    $body = read_json_body();
    if($body === null){
        json_response([
            'success' => false,
            'error' => 'Invalid JSON body'
        ]);
    }

    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $display_name = trim($body['display_name'] ?? '');
    $role = $body['role'] ?? 'viewer';

    if($email === '' || $password === ''){
        json_response([
            'success' => false,
            'error' => "Email and password required"
        ], 400);
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        json_response([
            'success' => false,
            'error' => 'Invalid email'
        ], 400);
    }

    if(!in_array($role, ['owner', 'admin', 'viewer'], true)){
        json_response([
            'success' => false,
            'error' => 'Invalid role'
        ], 400);
    }

    if(($_SESSION['user']['role'] ?? '') !== 'owner' && $role === 'owner'){
        json_response([
            'success' => false,
            'error' => 'Cannot create owner account'
        ], 403);
    }

    $checkStmt = $pdo->prepare("
        SELECT id FROM users WHERE email = :email LIMIT 1
    ");
    $checkStmt->execute([':email' => $email]);
    if($checkStmt->fetch()){
        json_response([
            'success' => false,
            'error' => 'Account cannot be created'
        ], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, display_name, role)
        VALUES (:email, :password_hash, :display_name, :role)
    ");
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':display_name' => $display_name !== '' ? $display_name : null,
        ':role' => $role
    ]);

    json_response([
        'success' => true,
        'data' => [
            'id' => (int)$pdo->lastInsertId(),
            'email' => $email,
            'display_name' => $display_name !== '' ? $display_name : null,
            'role' => $role
        ]
    ], 201);
}

if ($route === 'users' && $method === 'PUT') {
    requireRole(['owner', 'admin']);

    if ($id === null) {
        json_response([
            'success' => false, 
            'error' => 'PUT requires user id'
        ], 400);
    }

    $body = read_json_body();
    if ($body === null) {
        json_response([
            'success' => false, 
            'error' => 'Invalid JSON body'
        ], 400);
    }

    $stmt = $pdo->prepare("
        SELECT id, email, role
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $existing_user = $stmt->fetch();

    if (!$existing_user) {
        json_response(['success' => false, 'error' => 'User not found'], 404);
    }

    $current_role = $_SESSION['user']['role'] ?? '';

    if ($current_role !== 'owner' && $existing_user['role'] === 'owner') {
        json_response(['success' => false, 'error' => 'Only owner can modify owner account'], 403);
    }

    $fields = [];
    $params = [':id' => $id];

    if (array_key_exists('display_name', $body)) {
        $fields[] = 'display_name = :display_name';
        $display_name = trim((string)$body['display_name']);
        $params[':display_name'] = $display_name !== '' ? $display_name : null;
    }

    if (array_key_exists('role', $body)) {
        $new_role = $body['role'];

        if (!in_array($new_role, ['owner', 'admin', 'viewer'], true)) {
            json_response(['success' => false, 'error' => 'Invalid role'], 400);
        }

        if ($current_role !== 'owner' && $new_role === 'owner') {
            json_response(['success' => false, 'error' => 'Only owner can assign owner role'], 403);
        }

        if ($existing_user['role'] === 'owner' && $new_role !== 'owner') {
            json_response(['success' => false, 'error' => 'Owner account cannot be demoted'], 403);
        }

        $fields[] = 'role = :role';
        $params[':role'] = $new_role;
    }

    if (array_key_exists('password', $body)) {
        $password = $body['password'] ?? '';
        if (!is_string($password) || $password === '') {
            json_response(['success' => false, 'error' => 'Invalid password'], 400);
        }

        $fields[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    if (count($fields) === 0) {
        json_response(['success' => false, 'error' => 'No updatable fields provided'], 400);
    }

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    json_response([
        'success' => true,
        'data' => ['updated' => $updateStmt->rowCount()]
    ], 200);
}

if ($route === 'users' && $method === 'DELETE') {
    requireRole(['owner', 'admin']);

    if ($id === null) {
        json_response(['success' => false, 'error' => 'DELETE requires user id'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT id, email, role
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $existing_user = $stmt->fetch();

    if (!$existing_user) {
        json_response(['success' => false, 'error' => 'User not found'], 404);
    }

    $currentUserId = $_SESSION['user']['id'] ?? null;
    $currentRole = $_SESSION['user']['role'] ?? '';

    if ($existing_user['role'] === 'owner') {
        json_response(['success' => false, 'error' => 'Owner account cannot be deleted'], 403);
    }

    if ($currentUserId !== null && (int)$existing_user['id'] === (int)$currentUserId) {
        json_response(['success' => false, 'error' => 'You cannot delete your own account'], 403);
    }

    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $deleteStmt->execute([':id' => $id]);

    json_response([
        'success' => true,
        'data' => ['deleted' => $deleteStmt->rowCount()]
    ], 200);
}

if ($method === 'GET' && $route === 'me') {
    if (empty($_SESSION['user'])) {
        json_response([
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    json_response([
        'success' => true,
        'data' => $_SESSION['user']
    ], 200);
}

json_response(["error" => "Method not allowed"], 405);
