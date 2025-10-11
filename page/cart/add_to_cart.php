<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$payload   = json_decode(file_get_contents('php://input'), true);
$productId = (int)($payload['id']  ?? 0);
$qty       = (int)($payload['qty'] ?? 1);
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid id']);
    exit;
}
if ($qty <= 0) $qty = 1;

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// เพิ่มจำนวน (ต้องมี unique key (user_id, product_id) บนตาราง cart)
$sql = "INSERT INTO cart (user_id, product_id, quantity, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
$pdo->prepare($sql)->execute([$userId, $productId, $qty]);

// จำนวนชิ้นสินค้านี้ในตะกร้าหลังเพิ่ม
$stm = $pdo->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
$stm->execute([$userId, $productId]);
$itemQty = (int)$stm->fetchColumn();

// จำนวนรวมทั้งหมดในตะกร้า (sum ของ quantity)
$stm2 = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id=?");
$stm2->execute([$userId]);
$cartCount = (int)$stm2->fetchColumn();

echo json_encode([
    'ok'         => true,
    'item_qty'   => $itemQty,   // จำนวนของสินค้านี้ในตะกร้า
    'cart_count' => $cartCount  // จำนวนรวมทั้งหมด
]);
