<?php
// /page/backend/check_email.php
// ✅ หน้าที่ของไฟล์นี้: ตรวจสอบว่าอีเมลที่ผู้ใช้กรอก "มีอยู่ในระบบหรือยัง"

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php'; // มี $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
// ✅ ตรวจสอบความถูกต้องของอีเมล
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'reason' => 'invalid_email']);
    exit;
}
// ✅ เตรียม query ตรวจว่ามีอีเมลนี้อยู่ในตาราง users แล้วหรือยัง
$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

// ✅ ถ้ามีข้อมูล -> แปลว่าอีเมลนี้ถูกใช้แล้ว
$taken = $stmt->num_rows > 0;
$stmt->close();

echo json_encode(['ok' => true, 'taken' => $taken]);
