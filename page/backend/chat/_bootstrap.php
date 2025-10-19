<?php
// /page/backend/chat/_bootstrap.php
session_start();
require __DIR__ . '/../config.php'; // ใช้ config ของคุณ (mysqli $conn)

header('Content-Type: application/json; charset=utf-8');

/** บังคับต้องล็อกอิน */
function require_login(): int {
  if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
  }
  return (int)$_SESSION['user_id'];
}
