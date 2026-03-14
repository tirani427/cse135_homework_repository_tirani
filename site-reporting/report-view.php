<?php
session_start();

$cfg = require "/etc/cse135/collector_db.php";

try{
    $pdo = new PDO(
        $cfg["dsn"],
        $cfg["user"],
        $cfg["pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Exception $e){
    //http_response_code(500);
    header("Location: /500.html");
    exit();
}

$token = $_GET['token'] ?? '';
if($token === ''){
    header("Location: /404.html");
    exit();
}

$stmt-> $pdo->prepare("
    SELECT title, snapshot, created_at
    FROM saved_reports
    WHERE share_token = :token
    LIMIT 1
");

$stmt->execute([':token' => $token]);
$row = $stmt->fetch();

if(!$row){
    header("Location: /404.html");
    exit();
}

$snapshot = json_decode($row['snapshot'], true);
if(!is_array($snapshot)){
    header("Location: /500.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($row['title'])?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f5f5;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .header {
                background: #2E86C1;
                color: white;
                padding: 20px 24px;
            }
            .container {
                max-width: 1000px;
                margin: 24px auto;
                padding: 0 20px;
            }
            .panel {
                background: white;
                border-radius: 8px;
                padding: 24px;
                margin-bottom: 24px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .panel h2 {
                color: #2E86C1;
                margin-bottom: 12px;
            }
            pre {
                white-space: pre-wrap;
                font-family: inherit;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= htmlspecialchars($snapshot['title'] ?? 'Saved Report')?></h1>
            <div><?= htmlspecialchars(($snapshot['start'] ?? '') . ' to ' . ($snapshot['end'] ?? ''))?></div>
        </div>

        <div class="container">
            <?php foreach (($snapshot['sections'] ?? []) as $name => $section):?>
                <div class="panel">
                    <h2><?= htmlspecialchars(ucfirst($name))?></h2>
                    <?php if(!empty($section['comment'])):?>
                        <p><strong>Analyst Comment:</srtong><?= nl2br(htmlspecialchars($section['comment']))?></p>
                        <br>
                    <?php endif;?>
                    <pre><?= htmlspecialchars(json_encode($section, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
</html>


