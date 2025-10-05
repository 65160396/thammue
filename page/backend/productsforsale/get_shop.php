<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ====== ตั้งค่าฐานข้อมูล ======
$dsn  = 'mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connect_failed']);
    exit;
}

// ====== ดึง user_id ======
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId && isset($_GET['user_id'])) {
    // สำหรับทดสอบใน browser
    $userId = (int)$_GET['user_id'];
}

if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// ====== ดึงข้อมูลร้านจากตาราง shops ======
$stmt = $pdo->prepare("
  SELECT id, shop_name, status
  FROM shops
  WHERE user_id = ?
  LIMIT 1
");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

// ====== ตอบกลับเป็น JSON ======
echo json_encode([
    'ok'   => true,
    'shop' => $shop ?: null
]);
