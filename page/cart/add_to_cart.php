<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$payload = json_decode(file_get_contents('php://input'), true);
$productId = (int)($payload['id'] ?? 0);
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid id']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// toggle
$stm = $pdo->prepare("SELECT 1 FROM cart WHERE user_id=? AND product_id=?");
$stm->execute([$userId, $productId]);

if ($stm->fetchColumn()) {
    $pdo->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?")->execute([$userId, $productId]);
    $inCart = false;
} else {
    $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE quantity = quantity")->execute([$userId, $productId]);
    $inCart = true;
}

// นับจำนวนทั้งหมดในตะกร้า
$stm2 = $pdo->prepare("SELECT COUNT(*) AS cnt FROM cart WHERE user_id=?");

$stm2->execute([$userId]);
$cartCount = (int)$stm2->fetchColumn();

echo json_encode(['in_cart' => $inCart, 'cart_count' => $cartCount]);
exit;
