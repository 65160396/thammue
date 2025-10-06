<?php
// /page/backend/productsforsale/get_shop.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ===== DB (จะย้ายไปใช้ config.php ก็ได้) =====
$dsn  = 'mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'code' => 'DB_CONNECT_FAILED']);
    exit;
}

// ===== ต้องล็อกอินก่อน =====
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['ok' => false, 'code' => 'NOT_LOGIN']);
    exit;
}

// ===== หา Shop ของ user นี้ =====
$stmt = $pdo->prepare("
    SELECT id, shop_name AS name, status
    FROM shops
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if ($shop) {
    echo json_encode(['ok' => true, 'shop' => $shop]);
} else {
    echo json_encode(['ok' => false, 'code' => 'NO_SHOP']);
}
