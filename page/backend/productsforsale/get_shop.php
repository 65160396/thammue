<?php
// /page/backend/productsforsale/get_shop.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$dsn  = 'mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'code' => 'DB_CONNECT_FAILED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ต้องล็อกอินก่อน (เหมือนเดิม)
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['ok' => false, 'code' => 'NOT_LOGIN'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ดึงร้านของ user นี้ โดยเลือกฟิลด์ให้ครบ
$stmt = $pdo->prepare("
  SELECT
    id,
    shop_name,
    email,
    phone,
    pickup_addr,
    status
  FROM shops
  WHERE user_id = ?
  LIMIT 1
");
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'code' => 'NO_SHOP'], JSON_UNESCAPED_UNICODE);
    exit;
}

// เพื่อความเข้ากันได้ย้อนหลัง: คืนทั้ง shop_name และ name
$shop = [
    'id'          => (int)$row['id'],
    'shop_name'   => $row['shop_name'],
    'name'        => $row['shop_name'],   // สำเนาเดิม
    'email'       => $row['email'],
    'phone'       => $row['phone'],
    'pickup_addr' => $row['pickup_addr'],
    'status'      => $row['status'],
];

echo json_encode(['ok' => true, 'shop' => $shop], JSON_UNESCAPED_UNICODE);
