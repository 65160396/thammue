<?php
session_start();
require __DIR__ . '/../backend/config.php'; // ✅ ใช้ path นี้

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // เชื่อมต่อ DB
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // รับค่าจากฟอร์ม
    $shop_id     = (int)($_POST['shop_id'] ?? 0);
    $shop_name   = trim($_POST['shop_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $pickup_addr = trim($_POST['pickup_addr'] ?? '');

    if ($shop_id <= 0)     throw new Exception('ไม่พบรหัสร้าน');
    if ($shop_name === '') throw new Exception('กรุณากรอกชื่อร้าน');
    if ($email === '')     throw new Exception('กรุณากรอกอีเมล');

    // ตรวจสอบสิทธิ์เจ้าของร้าน
    $stmt = $pdo->prepare("SELECT id FROM shops WHERE id = ? AND user_id = ?");
    $stmt->execute([$shop_id, $userId]);
    if (!$stmt->fetch()) throw new Exception('ไม่มีสิทธิ์แก้ไขร้านนี้');

    // อัปเดตข้อมูลร้าน
    $stmt = $pdo->prepare("
        UPDATE shops
        SET shop_name   = :shop_name,
            email       = :email,
            phone       = :phone,
            pickup_addr = :pickup_addr,
            updated_at  = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':shop_name'   => $shop_name,
        ':email'       => $email,
        ':phone'       => $phone,
        ':pickup_addr' => $pickup_addr,
        ':id'          => $shop_id,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
