<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$type = $_GET['type'] ?? 'product';
if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';
$id   = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad id']);
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type, $id]);
$likeCount = (int)$st->fetchColumn();

$likedByMe = false;
if ($userId > 0) {
    $st = $pdo->prepare("SELECT 1 FROM favorites WHERE item_type=? AND item_id=? AND user_id=?");
    $st->execute([$type, $id, $userId]);
    $likedByMe = (bool)$st->fetchColumn();
}

// นับ “ขายแล้ว” สำหรับสินค้าเท่านั้น (แก้ query ให้ตรง schema orders ของคุณ)
$sold = 0;
if ($type === 'product') {
    $st = $pdo->prepare("SELECT COALESCE(SUM(quantity),0)
                       FROM order_items oi
                       JOIN orders o ON o.id=oi.order_id
                      WHERE oi.product_id=? AND o.status IN ('paid','shipped','completed')");
    $st->execute([$id]);
    $sold = (int)$st->fetchColumn();
}

echo json_encode([
    'like_count'  => $likeCount,
    'liked_by_me' => $likedByMe,
    'sold_count'  => $sold
], JSON_UNESCAPED_UNICODE);
