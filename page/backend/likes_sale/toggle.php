<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $in['type'] ?? 'product';
if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';
$id   = (int)($in['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad id']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$st = $pdo->prepare("SELECT 1 FROM favorites WHERE item_type=? AND item_id=? AND user_id=?");
$st->execute([$type, $id, $userId]);
$exists = (bool)$st->fetchColumn();

if ($exists) {
    $st = $pdo->prepare("DELETE FROM favorites WHERE item_type=? AND item_id=? AND user_id=?");
    $st->execute([$type, $id, $userId]);
    $liked = false;
} else {
    $st = $pdo->prepare("INSERT INTO favorites(item_type,item_id,user_id) VALUES (?,?,?)");
    $st->execute([$type, $id, $userId]);
    $liked = true;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type, $id]);
$count = (int)$st->fetchColumn();

echo json_encode(['liked' => $liked, 'count' => $count], JSON_UNESCAPED_UNICODE);
