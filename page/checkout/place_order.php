<?php
// /page/place_order.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pay = $_POST['pay_method'] ?? 'qr';
$total = (float)($_POST['total'] ?? 0);

// TODO: บันทึกลงตาราง orders / order_items ตามโครงของโปรเจกต์
// ตัวอย่าง: เคลียร์ cart หลัง “สั่งซื้อ”
$pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);

// ส่งผู้ใช้ไปหน้าเสร็จสิ้น
header("Location: /page/order_success.html?method={$pay}&total={$total}");
exit;
