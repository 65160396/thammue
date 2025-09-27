<?php
// 1) กันเปิดตรง
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /page/add_member.html');
    exit;
}

// 2) โหลด config + เช็ค $conn
$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    http_response_code(500);
    exit("config.php not found");
}
require_once $cfg;
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("DB not ready");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// 3) รับค่า
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm_password'] ?? '');
$name     = trim($_POST['name'] ?? '');

// 4) ตรวจ
$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
if (mb_strlen($password) < 8)                                     $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
if ($password !== $confirm)                                       $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
if ($name === '')                                                 $errors[] = 'กรุณากรอกชื่อผู้ใช้';

if ($errors) {
    $msg = rawurlencode(implode(' • ', $errors));
    header("Location: /page/add_member.html?type=error&msg={$msg}");
    exit;
}

// 5) เช็คอีเมลซ้ำ
$stmt = $conn->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $msg = rawurlencode('อีเมลนี้ถูกใช้งานแล้ว');
    header("Location: /page/add_member.html?type=error&msg={$msg}");
    exit;
}
$stmt->close();

// 6) บันทึก
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins  = $conn->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');
$ins->bind_param('sss', $name, $email, $hash);
$ins->execute();
$ins->close();
$conn->close();

// 7) สมัครสำเร็จ -> แจ้งบนฟอร์ม หรือจะพาไป login ก็ได้
$msg = rawurlencode('สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
header("Location: /page/add_member.html?type=success&msg={$msg}");
exit;
