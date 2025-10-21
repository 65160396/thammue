<?php
// /page/store/get_my_shop.php
session_start();
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized', 401);
    $userId = (int)$_SESSION['user_id'];

    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ถ้ามีร้านหลายร้าน เลือกอันแรกสุดที่สร้าง
    $st = $pdo->prepare("SELECT id, shop_name FROM shops WHERE user_id=? ORDER BY id ASC LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) throw new Exception('no_shop', 404);

    echo json_encode(['ok' => true, 'shop' => $row]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
