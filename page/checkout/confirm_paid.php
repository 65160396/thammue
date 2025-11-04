<?php
// /page/checkout/confirm_paid.php
// ✅ หน้านี้ใช้เปลี่ยนสถานะคำสั่งซื้อเป็น "ชำระเงินแล้ว" (paid)
//    มักถูกเรียกหลังผู้ใช้กดปุ่ม "ยืนยันการชำระเงิน"
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location:/page/login.html');
    exit;
}
$userId  = (int)$_SESSION['user_id'];

// ✅ รับค่า order_id จากแบบฟอร์ม (POST)
$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
     // ไม่มี id → กลับหน้าหลัก
    header('Location:/');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ✅ ตรวจสอบสิทธิ์: ต้องเป็นออเดอร์ของ user คนนี้เท่านั้น
$stmt = $pdo->prepare("SELECT id,status FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$orderId, $userId]);
$ord = $stmt->fetch();
if (!$ord) {
    http_response_code(403);
    exit('ไม่พบคำสั่งซื้อ');
}

// ✅ อัปเดตสถานะออเดอร์เป็น 'paid' และบันทึกเวลา paid_at
// อัปเดตเป็น paid (โปรเจ็กต์เดโม่ – ไม่มีสลิป)
$pdo->prepare("
  UPDATE orders
  SET status='paid', paid_at=NOW()
  WHERE id=? AND user_id=? AND status IN ('pending_payment','cod_pending')
")->execute([$orderId, $userId]);

unset($_SESSION['checkout_total']);
// ✅ ส่งผู้ใช้ไปหน้าแสดงผลการสั่งซื้อสำเร็จ
header("Location: /page/checkout/order_success.php?order_id=" . $orderId);
