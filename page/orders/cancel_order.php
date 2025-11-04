<?php
// /page/orders/cancel_order.php
// ✅ ใช้สำหรับให้ผู้ใช้ "ยกเลิกคำสั่งซื้อ" ด้วยตัวเอง


session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: /page/login.html');
  exit;
}
$userId  = (int)$_SESSION['user_id'];
// ✅ รับค่า order_id ที่ส่งมาจากฟอร์ม
$orderId = (int)($_POST['id'] ?? 0);
if ($orderId <= 0) {
  // ไม่มี id → กลับหน้ารายการคำสั่งซื้อ
  header('Location: /page/orders');
  exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
// ✅ ตรวจสอบว่าออเดอร์นี้เป็นของผู้ใช้คนนี้จริงไหม
$st = $pdo->prepare("SELECT id,user_id,status,paid_at FROM orders WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$orderId, $userId]);
$ord = $st->fetch();
if (!$ord) {
  http_response_code(404);
  exit('ไม่พบคำสั่งซื้อ');
}
// ✅ ยกเลิกได้เฉพาะออเดอร์ที่ยังไม่ชำระ (pending)
if (!in_array($ord['status'], ['pending_payment', 'cod_pending']) || !empty($ord['paid_at'])) {
  // ถ้าชำระแล้วหรือสถานะอื่น → กลับไปดูรายละเอียดออเดอร์แทน
  header('Location: /page/orders/view.php?id=' . (int)$orderId);
  exit;
}
// ✅ อัปเดตสถานะเป็น 'cancelled' พร้อมเหตุผล user_cancel และบันทึกเวลา
$up = $pdo->prepare("UPDATE orders
                     SET status='cancelled', cancelled_at=NOW(), cancel_reason='user_cancel'
                     WHERE id=?");
$up->execute([$orderId]);
// ✅ กลับไปหน้ารายละเอียดคำสั่งซื้อหลังยกเลิกสำเร็จ
header('Location: /page/orders/view.php?id=' . (int)$orderId);
