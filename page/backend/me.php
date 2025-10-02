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
