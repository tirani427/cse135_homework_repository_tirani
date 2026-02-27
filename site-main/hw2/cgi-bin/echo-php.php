<?php
header("Cache-Control: no-cache");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$query = $_SERVER["QUERY_STRING"] ?? "";
$content_type = $SERVER["CONTENT_TYPE"] ?? "";

$raw_body = file_get_contents("php://input");

$params = [];

if($method == "GET"){
	$params = $_GET;
} else {
	if(stripos($content_type, "applicatio/json") === 0){
		if($raw_body !== ""){
			$decoded = json_decode($raw_body, true);
			if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
				$params = $decoded;
			} else {
				$params = [
					"_json_error" => "invalid json",
					"_raw" => $raw_body
				];
			}
		} else {
			$params = new stdClass();
		}
	} else {
		if($method === "POST"){
			$params = $_POST;
		} else {
			parse_str($raw_body, $params);
		}
	}
}

$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$user_agent = $_SEVER["HTTP_USER_AGENT"] ?? "unknown";
$host = gethostname();
$time = (new DateTime())->format(DateTime::ATOM);

$response = [
	"endpoint" => "echo-php",
	"method" => $method,
	"content_type" => $content_type,
	"query_string" => $query,
	"params" => $params,
	"raw_body" => $raw_body,
	"meta" => [
		"hostname" => $host,
		"time" => $time,
		"user_agent" => $user_agent,
		"ip" => $ip
	]
];

if(stripos($content_type, "application/json") === 0){
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($response, JSON_PRETTY_PRINT);
	exit;
}

header("Content-Type: text/html; charset=utf-8");

function one($x){
	if ($x ===  null) return "";
	if(is_array($x)) return count($x) ? strval($x[0]) : "";
	return strval($x);
}

$name = one($params["name"] ?? "");
$message = one($params["message"] ?? "");
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title> Echo (PHP) </title>
</head>

<body>
	<h2> Echo (x-www-form-urlencoded) </h2>
	<ul>
		<li>name= <?= htmlspecialchars($name)?></li>
		<li>message= <?= htmlspecialchars($message)?></li>
	</ul>
	
	<p><b>Method:</b> <?=htmlspecialchars($method)?></p>
	<p><b>Content-Type:</b> <?= htmlspecialchars($content_type) ?></p>
	<p><b>Query-String:</b> <?= htmlspecialchars($query) ?></p>
    	<p><b>IP:</b> <?= htmlspecialchars($ip) ?></p>
    	<p><b>User-Agent:</b> <?= htmlspecialchars($user_agent) ?></p>
    	<p><b>Time:</b> <?= htmlspecialchars($time) ?></p>
</body>
</html>
