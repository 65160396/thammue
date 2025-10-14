<?php
// /page/checkout/confirm_paid.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location:/page/login.html');
    exit;
}
$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    header('Location:/');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ตรวจสิทธิ์
$stmt = $pdo->prepare("SELECT id,status FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$orderId, $userId]);
$ord = $stmt->fetch();
if (!$ord) {
    http_response_code(403);
    exit('ไม่พบคำสั่งซื้อ');
}

// อัปเดตเป็น paid (โปรเจ็กต์เดโม่ – ไม่มีสลิป)
$pdo->prepare("
  UPDATE orders
  SET status='paid', paid_at=NOW()
  WHERE id=? AND user_id=? AND status IN ('pending_payment','cod_pending')
")->execute([$orderId, $userId]);

unset($_SESSION['checkout_total']);
header("Location: /page/checkout/order_success.php?order_id=" . $orderId);
