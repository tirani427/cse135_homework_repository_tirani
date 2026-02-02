
<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

$query = $_SERVER["QUERY_STRING"] ?? "";
$params = $_GET;
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>GET Request Echo</title>
</head>

<body>
	<h1 align="center">GET Request Echo with PHP</h1>
	<hr/>
	<p><b>Query String:</b> <?= htmlspecialchars($query)?></p>
<?php
foreach ($params as $key => $value){
	if(is_array($value)){
		foreach ($value as $v){
			echo htmlspecialchars($key) . " = " . htmlspecialchars($v) . "<br/>\n";
		}
	} else {
		echo htmlspecialchars($key) . " = " . htmlspecialchars($value) . "<br/>\n";
	}
}
?>
</body>
</html>
