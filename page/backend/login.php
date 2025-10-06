<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /page/login.html');
    exit;
}
require_once __DIR__ . '/config.php'; // ต้องมี $conn (mysqli)
session_start();

$identifier = trim($_POST['identifier'] ?? '');
$password   = (string)($_POST['password'] ?? '');

if ($identifier === '' || $password === '') {
    $msg = rawurlencode('กรุณากรอกข้อมูลให้ครบ');
    header("Location: /page/login.html?type=error&msg={$msg}");
    exit;
}

// หาได้ทั้งอีเมลหรือชื่อผู้ใช้
$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
$sql = $isEmail
  ? 'SELECT id,username AS name,email,password_hash FROM users WHERE email=? LIMIT 1'
  : 'SELECT id,username AS name,email,password_hash FROM users WHERE username=? LIMIT 1';

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $msg = rawurlencode('ไม่พบผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
    header("Location: /page/login.html?type=error&msg={$msg}");
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];

$msg = rawurlencode('เข้าสู่ระบบสำเร็จ!');
header("Location: /page/main.html?type=success&msg={$msg}"); // กลับหน้า .html ได้
exit;
