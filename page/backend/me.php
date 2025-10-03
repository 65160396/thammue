<?php
// page/backend/me.php (backward-compatible, additive)
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$S = $_SESSION['user'] ?? null;

// รองรับทั้ง 2 รูปแบบ session: แบบแยก key และแบบรวมใน $_SESSION['user']
$userId   = $_SESSION['user_id']   ?? ($S['id']    ?? null);
$userName = $_SESSION['user_name'] ?? ($S['name']  ?? '');
$userMail = $_SESSION['user_email'] ?? ($S['email'] ?? '');
$username = $_SESSION['username']  ?? ($S['username'] ?? null);
$avatar   = $_SESSION['user_avatar'] ?? ($S['avatar'] ?? null);
if (empty($userId)) {
  echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
  exit;
}

// คำนวณ display_name: ชอบ name > username > ชื่อก่อน @ ของอีเมล
$display = trim($userName);
if ($display === '' && !empty($username)) $display = $username;
if ($display === '' && !empty($userMail)) $display = strstr($userMail, '@', true);

echo json_encode([
  'ok'   => true,
  'user' => [
    // ===== ของเดิม (อย่าลบ) =====
    'id'    => (int)$userId,
    'name'  => $userName,
    'email' => $userMail,
    // ===== ของใหม่ (เสริม) =====
    'username'     => $username,
    'display_name' => $display,
    'avatar'       => $avatar,
  ],
  'ts' => time(), // เผื่อ frontend กัน cache
], JSON_UNESCAPED_UNICODE);
