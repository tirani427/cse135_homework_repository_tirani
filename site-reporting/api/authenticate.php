<?php

function authenticate(PDO $pdo, string $email, string $password): ?array {
    if($email === '' || $password === ''){
        return null;
    }

    if(session_status() !== PHP_SESSION_ACTIVE){
        session_start();
    }

    $stmt = $pdo->prepare(
        'SELECT id, email, password_hash, display_name, role
        FROM users
        WHERE email = :email
        LIMIT 1'
    );

    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        return null;
    }

    if(!password_verify($password, $user['password_hash'])){
        return null;
    }

   return ([
    'id' => (int)$user['id'],
    'email' => $user['email'],
    'displayName' => $user['display_name'],
    'role' => $user['role']
   ]);
}