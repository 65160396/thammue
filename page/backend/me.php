<?php
// /page/backend/me.php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$S = $_SESSION['user'] ?? null;
$userId    = $_SESSION['user_id']    ?? ($S['id']    ?? null);
$userName  = $_SESSION['user_name']  ?? ($S['name']  ?? '');
$userMail  = $_SESSION['user_email'] ?? ($S['email'] ?? '');
$userPhone = $_SESSION['user_phone'] ?? ($S['phone'] ?? '');
$username  = $_SESSION['username']   ?? ($S['username'] ?? null);
$avatar    = $_SESSION['user_avatar'] ?? ($S['avatar'] ?? null);

if (!$userId) {
  echo json_encode(['ok' => false, 'code' => 'NOT_LOGIN']);
  exit;
}

// ถ้า email/phone ว่าง ให้ลองดึงจาก DB
if ($userMail === '' || $userPhone === '') {
  try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4', 'root', '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $st = $pdo->prepare('SELECT email, phone, display_name FROM users WHERE id=? LIMIT 1');
    $st->execute([$userId]);
    if ($u = $st->fetch()) {
      if ($userMail  === '') $userMail  = $u['email']  ?? '';
      if ($userPhone === '') $userPhone = $u['phone']  ?? '';
      if ($userName  === '' && !empty($u['display_name'])) $userName = $u['display_name'];
    }
  } catch (Throwable $e) {
  }
}

$display = trim($userName) ?: ($username ?: ($userMail ? strstr($userMail, '@', true) : ''));

session_write_close(); // ปลดล็อก session ให้ request อื่นวิ่งคู่กันได้เร็วขึ้น

echo json_encode([
  'ok' => true,
  'user' => [
    'id' => (int)$userId,
    'name' => $userName,
    'email' => $userMail,
    'phone' => $userPhone,
    'username' => $username,
    'display_name' => $display,
    'avatar' => $avatar,
  ],
  'ts' => time(),
], JSON_UNESCAPED_UNICODE);
