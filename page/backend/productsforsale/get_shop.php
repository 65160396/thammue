<?php
// /page/backend/get_shop.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ต้องล็อกอินก่อน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

// เชื่อมต่อ DB (แก้ค่าตามจริง)
$dsn = "mysql:host=localhost;dbname=thammue;charset=utf8mb4";
$dbUser = "root";
$dbPass = "";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

// ดึงร้านของ user
$stmt = $pdo->prepare("SELECT id AS shop_id, shop_name FROM shop WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $userId]);
$shop = $stmt->fetch();

if (!$shop) {
    echo json_encode(['user_id' => $userId, 'shop_id' => null, 'shop_name' => null]);
    exit;
}

echo json_encode([
    'user_id'   => $userId,
    'shop_id'   => (int)$shop['shop_id'],
    'shop_name' => $shop['shop_name']
]);
