<?php
// /page/backend/register.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../add_member.html');
    exit;
}

require_once __DIR__ . '/config.php'; // ต้องมี $conn = new mysqli(...)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$email   = trim($_POST['email']   ?? '');
$pass    = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm']  ?? '');
$name    = trim($_POST['display'] ?? ''); // display = ชื่อผู้ใช้

$errors = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
if (mb_strlen($pass) < 8)                        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
if ($pass !== $confirm)                          $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
if ($name === '')                                $errors[] = 'กรุณากรอกชื่อผู้ใช้';

if ($errors) {
    $msg = rawurlencode(implode(' • ', $errors));
    header("Location: ../add_member.html?type=error&msg={$msg}");
    exit;
}

// เช็คอีเมลซ้ำ
$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    header('Location: ../add_member.html?type=error&msg=' . rawurlencode('อีเมลนี้ถูกใช้งานแล้ว'));
    exit;
}
$stmt->close();

// บันทึก
$hash       = password_hash($pass, PASSWORD_DEFAULT);
$isVerified = 0; // สมัครใหม่ให้ยังไม่ยืนยันอีเมล

$ins = $conn->prepare('
    INSERT INTO users (name, email, password_hash, is_verified, created_at)
    VALUES ( ?, ?, ?, ?, NOW() )
');
$ins->bind_param('sssi', $name, $email, $hash, $isVerified);
$ins->execute();
$ins->close();
$conn->close();

// สมัครสำเร็จ → ไป success.html
header('Location: ../success.html');
exit;
