<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if (empty($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// เลือกสูตรเดียวกันทั้งระบบ: COUNT(*) = จำนวนชนิด / SUM(quantity) = จำนวนชิ้นรวม
$st = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id=?");
$st->execute([(int)$_SESSION['user_id']]);
echo json_encode(['count' => (int)$st->fetchColumn()]);
