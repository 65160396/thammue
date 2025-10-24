<?php
// /page/backend/ex__common.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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

/** ใช้ฐาน shopdb_ex สำหรับฟีเจอร์แลกเปลี่ยน */
function dbx(): mysqli {
  $m = new mysqli('127.0.0.1', 'root', '', 'shopdb_ex'); // << เปลี่ยนตรงนี้
  if ($m->connect_error) {
    jerr('db_connect_failed', 500);
  }
  $m->set_charset('utf8mb4');
  return $m;
}
