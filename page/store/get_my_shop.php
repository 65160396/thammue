<?php
// /page/store/get_my_shop.php
session_start();
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // ✅ ต้องล็อกอินก่อนถึงจะดึงร้านตัวเองได้
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized', 401);
    $userId = (int)$_SESSION['user_id'];
// ✅ เชื่อมต่อฐานข้อมูลด้วย PDO (โหมดโยน Exception + FETCH_ASSOC)
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

   // ✅ หา “ร้านของฉัน” จากตาราง shops (ถ้ามีหลายร้าน เลือกอันที่สร้างก่อนสุด)
    $st = $pdo->prepare("SELECT id, shop_name FROM shops WHERE user_id=? ORDER BY id ASC LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) throw new Exception('no_shop', 404);

    echo json_encode(['ok' => true, 'shop' => $row]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
