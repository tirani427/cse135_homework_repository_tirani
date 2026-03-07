<?php

define('SESSION_TIMEOUT_SECONDS', 1800);

function sessionize(PDO $pdo, array $beacon): void {
    $sessionId = $beacon['sessionId'] ?? '';
    if($sessionId === '') return;

    $url = $beacon['url'] ?? null;
    if($url === null) return;
    
    $referrer = $beacon['referrer'] ?? null;
    $userAgent = $beacon['userAgent'] ?? null;

    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'SELECT id, last_activity FROM sessions WHERE session_id = ? ORDER BY last_activity DESC LIMIT 1'
    );
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row){
        $last_activity = strtotime($row['last_activity']);
        $gap = time() - $last_activity;

        if($gap <= SESSION_TIMEOUT_SECONDS) {
            $update = $pdo->prepare(
                'UPDATE sessions SET last_page = ?, page_count = page_count + 1, last_activity = ?, duration_seconds = TIMESTAMPDIFF(SECOND, start_time, ?) WHERE id = ?'
            );
            $update->execute([$url, $now, $now, $row['id']]);
            return;
        }
    }
    $insert = $pdo->prepare(
        'INSERT INTO sessions (session_id, first_page, last_page, page_count, start_time, last_activity, duration_seconds, referrer, user_agent) VALUES (?,?,?,1,?,?,0,?,?)'
    );
    $insert->execute([$sessionId, $url, $url, $now, $now, $referrer, $userAgent]);
    
}