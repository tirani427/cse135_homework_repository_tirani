<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=utf-8");

$raw = file_get_contents("php://input");

$params = $_POST;

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>POST Request Echo </title>
</head>

<body>
	<h1 align="center">POST Request Echo with PHP</h1>
	<hr/>

	<p><b>Message Body (raw):</b><br/><?= htmlspecialchars($raw)?></p>

	<b>Message Body (parsed):</b><br/>
	<ul>
		<?php foreach ($params as $key => $value):?>
			<?php if(is_array($value)):?>
				<?php foreach($value as $V):?>
				<li><?= htmlspecialchars($key)?> = <?= htmlspecialchars($v)?></li>
				<?php endforeach;?>
			<?php else: ?>
				<li><?= htmlspecialchars($key)?> = <?= htmlspecialchars($value)?></li>
			<?php endif;?>
		<?php endforeach; ?>
	</ul>
</body>
</html>
						
