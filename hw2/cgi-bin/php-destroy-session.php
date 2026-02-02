<?php
header("Cache-Control: no-cache");
session_start();

// Clear session data
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 3600,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>PHP Session Destroyed</title>
</head>
<body>
  <h1>Session Destroyed</h1>
  <a href="/php-cgiform.html">Back to the PHP CGI Form</a><br />
  <a href="./php-sessions-1.php">Back to Page 1</a><br />
  <a href="./php-sessions-2.php">Back to Page 2</a>
</body>
</html>
