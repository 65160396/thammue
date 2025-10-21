<?php
require __DIR__ . '/_config_admin.php';
header('Content-Type: application/json; charset=utf-8');

// ตอบ JSON เมื่อถูกเรียกแบบ GET เพื่อเช็คพาธได้ง่าย (ไม่โชว์หน้า 405)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'use_post'], JSON_UNESCAPED_UNICODE);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = trim($in['email'] ?? '');
$pass  = (string)($in['password'] ?? '');

// ใช้ users (DB thammue) และตรวจ role=admin + password_hash
$pdo = db_for('exchange');
$st = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE email=:e LIMIT 1");
$st->execute([':e'=>$email]);
$row = $st->fetch();

if (!$row || !password_verify($pass, $row['password_hash']) || $row['role'] !== 'admin') {
  json_err('invalid_login', 401);
}

start_admin_session();
$_SESSION['admin_id'] = (int)$row['id'];
$csrf = ensure_csrf_token();

echo json_encode(['ok'=>true,'csrf'=>$csrf], JSON_UNESCAPED_UNICODE);
