<?php
session_start();
require __DIR__ . '/../backend/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized');

    // ✅ รับค่า product_id และ shop_id จากแบบฟอร์ม (POST)
    $pid = (int)$_POST['product_id'];
    $sid = (int)$_POST['shop_id'];
    $uid = (int)$_SESSION['user_id'];  // user_id ของเจ้าของร้าน

    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ ตรวจสอบสิทธิ์: สินค้านี้ต้องอยู่ในร้านนี้ และร้านนี้ต้องเป็นของผู้ใช้คนปัจจุบันเท่านั้น
    $chk = $pdo->prepare("SELECT p.id FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.id=? AND p.shop_id=? AND s.user_id=?");
    $chk->execute([$pid, $sid, $uid]);
    if (!$chk->fetch()) throw new Exception('forbidden'); // ❌ ไม่ใช่เจ้าของร้าน ห้ามลบ
  // ✅ ลบสินค้าออกจากฐานข้อมูล
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
