<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if (empty($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$cnt = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id={$userId}")->fetchColumn();
echo json_encode(['count' => $cnt]);
