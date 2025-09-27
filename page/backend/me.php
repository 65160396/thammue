<?php
// page/backend/me.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode([
    'ok'   => true,
    'user' => [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ],
]);
