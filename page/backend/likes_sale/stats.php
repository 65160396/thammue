<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$type = $_GET['type'] ?? 'product';
if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad id']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// นับจำนวนถูกใจทั้งหมด (สาธารณะ)
$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type, $id]);
$count = (int)$st->fetchColumn();

// ผู้ใช้คนนี้เคยกดไหม (ถ้าล็อกอิน)
$liked = false;
if ($userId > 0) {
    $st = $pdo->prepare("SELECT 1 FROM favorites WHERE item_type=? AND item_id=? AND user_id=? LIMIT 1");
    $st->execute([$type, $id, $userId]);
    $liked = (bool)$st->fetchColumn();
}

// sold_count: อิงสคีมาของคุณ (order_items.qty + status = 'paid')
$sold = 0;
if ($type === 'product') {
    $st = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM order_items WHERE product_id=? AND status='paid'");
    $st->execute([$id]);
    $sold = (int)$st->fetchColumn();
}

echo json_encode([
    'count'      => $count,      // ชื่อคีย์ให้ตรง JS
    'liked'      => $liked,      // ชื่อคีย์ให้ตรง JS
    'sold_count' => $sold        // เสริมได้ เผื่ออัปเดต badge ในหน้า
], JSON_UNESCAPED_UNICODE);
