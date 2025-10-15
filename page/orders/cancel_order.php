<?php
// /page/orders/cancel_order.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: /page/login.html');
  exit;
}
$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['id'] ?? 0);
if ($orderId <= 0) {
  header('Location: /page/orders');
  exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$st = $pdo->prepare("SELECT id,user_id,status,paid_at FROM orders WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$orderId, $userId]);
$ord = $st->fetch();
if (!$ord) {
  http_response_code(404);
  exit('ไม่พบคำสั่งซื้อ');
}

if (!in_array($ord['status'], ['pending_payment', 'cod_pending']) || !empty($ord['paid_at'])) {
  header('Location: /page/orders/view.php?id=' . (int)$orderId);
  exit;
}

$up = $pdo->prepare("UPDATE orders
                     SET status='cancelled', cancelled_at=NOW(), cancel_reason='user_cancel'
                     WHERE id=?");
$up->execute([$orderId]);

header('Location: /page/orders/view.php?id=' . (int)$orderId);
