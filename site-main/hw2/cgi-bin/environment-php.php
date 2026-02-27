<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Environment Variables</title>
</head>

<body>
	<h1 align="center">Environment Variables with PHP</h1>
	<hr/>

<?php

ksort($_SERVER);

foreach($_SERVER as $variable => $value){
	if(is_array($value)){
		$value = implode(", ", $value);
	}
	echo "<b>" . htmlspecialchars($variable) . ":</b>" . htmlspecialchars($value) . "<br/>\n";
}
?>

</body>
</html>
