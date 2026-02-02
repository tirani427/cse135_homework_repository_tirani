<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

$date = date("r");

$address = $_SERVER["REMOTE_ADDR"] ?? "unknown";
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title> Hello CGI World</title>
</head>

<body>
	<h1 align="center">Hello HTML World with PHP</h1>
	<hr />
	<p>Hello World</p>
	<p>This page was generated with PHP programming language</p>
	<p>Tia says hi again :D</p>

	<p>This program was generated at: <?= htmlspecialchars($date)?></p>
	<p>Your current IP address is: <?= htmlspecialchars($address)?></p>
</body>
</html>
