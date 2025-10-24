<?php
// /page/backend/ex__common.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * Config - ปรับค่าถ้าจำเป็น
 */
define('EX_DB_HOST', '127.0.0.1');
define('EX_DB_USER', 'root');
define('EX_DB_PASS', '');
define('EX_DB_NAME', 'shopdb_ex');

/* ตารางหลัก (แก้ครั้งเดียวที่นี่ถ้าชื่อ prefix เปลี่ยน) */
define('T_ITEMS', 'ex_items');
define('T_REQUESTS', 'ex_requests');
define('T_NOTIFICATIONS', 'ex_notifications');
define('T_FAVORITES', 'ex_favorites');
define('T_CHAT_ROOMS', 'ex_chat_rooms');
define('T_CHAT_PARTICIPANTS', 'ex_chat_participants');
define('T_CHAT_MESSAGES', 'ex_chat_messages');

function me(): int { return (int)($_SESSION['user_id'] ?? 0); }

function jerr(string $msg='error', int $code=400): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok(array $data=[]): void {
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

function dbx(): mysqli {
  $m = new mysqli(EX_DB_HOST, EX_DB_USER, EX_DB_PASS, EX_DB_NAME);
  if ($m->connect_error) jerr('db_connect_failed: '.$m->connect_error, 500);
  $m->set_charset('utf8mb4');
  return $m;
}

/** Helpers for mysqli_stmt results */
function stmt_all_assoc(mysqli_stmt $st): array {
  $rs = $st->get_result(); $out=[];
  if ($rs) while ($row=$rs->fetch_assoc()) $out[]=$row;
  return $out;
}
function stmt_one_assoc(mysqli_stmt $st): ?array {
  $rs = $st->get_result(); return $rs ? ($rs->fetch_assoc() ?: null) : null;
}
