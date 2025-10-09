<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'ต้องเข้าสู่ระบบก่อน'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$type = $payload['type'] ?? 'product';
if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';
$id = (int)($payload['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad id']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// มีอยู่แล้วหรือยัง
$st = $pdo->prepare("SELECT id FROM favorites WHERE item_type=? AND item_id=? AND user_id=? LIMIT 1");
$st->execute([$type, $id, $userId]);
$exists = $st->fetchColumn();

if ($exists) {
    $pdo->prepare("DELETE FROM favorites WHERE id=?")->execute([$exists]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO favorites(item_type,item_id,user_id) VALUES (?,?,?)")
        ->execute([$type, $id, $userId]);
    $liked = true;
}

// นับใหม่
$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type, $id]);
$count = (int)$st->fetchColumn();

echo json_encode([
    'liked' => $liked,  // ตรงกับ JS
    'count' => $count
], JSON_UNESCAPED_UNICODE);
