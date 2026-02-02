<?php
header("Cache-Control: no-cache");
session_start();

$name = $_SESSION["username"] ?? null;

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>PHP Sessions</title>
</head>

<body>
	<h1>PHP Sessions Page 2</h1>
	
	<?php if($name):?>
	<p><b>Name:</b> <?= htmlspecialchars($name)?></p>
	<?php else:?>
	<p><b>Name:</b> You do not have a name set</p>
	<?php endif;?>

	<br/><br/>
	<a href="./php-sessions-1.php">Session Page 1</a>
	<a href="/php-cgiform.html"> PHP CGI Form</a>

	<form style="margin-top:30px" action="./php-destroy-session.php" method="get">
    		<button type="submit">Destroy Session</button>
	</form>
</body>
</html>
