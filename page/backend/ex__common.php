<?php
// /page/backend/ex__common.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php'; // ให้บริการ db(): mysqli

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function me(): int {
  return (int)($_SESSION['user_id'] ?? 0);
}
function jerr(string $msg = 'error', int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok(array $data = []): void {
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}
function stmt_all_assoc(mysqli_stmt $stmt): array {
  $res = $stmt->get_result();
  return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function dbx(): mysqli { return db(); } // alias ชัด ๆ
