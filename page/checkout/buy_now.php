<?php
// /page/checkout/buy_now.php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload   = json_decode($raw, true) ?: [];
$productId = (int)($payload['id'] ?? 0);
$qty       = max(1, (int)($payload['qty'] ?? 1));

if ($productId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'bad_request']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$stm = $pdo->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
$stm->execute([$productId]);
if (!$stm->fetch()) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not_found']);
    exit;
}

$_SESSION['buy_now'] = ['product_id' => $productId, 'qty' => $qty];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'checkout' => '/page/checkout/checkout.php?mode=buy-now'
]);
