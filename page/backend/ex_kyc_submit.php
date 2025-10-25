<?php
// /page/backend/ex_kyc_submit.php
require_once __DIR__ . '/ex__common.php'; // uses shopdb_ex
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$full_name   = trim((string)($input['full_name'] ?? ''));
$national_id = preg_replace('/\D+/', '', (string)($input['national_id'] ?? ''));
$dob         = trim((string)($input['dob'] ?? ''));
$address     = trim((string)($input['address'] ?? ''));
$id_front    = trim((string)($input['id_front_url'] ?? ''));
$id_back     = trim((string)($input['id_back_url'] ?? ''));
$selfie      = trim((string)($input['selfie_url'] ?? ''));

if ($full_name==='' || $national_id==='' || $dob==='' || $address==='' || $id_front==='' || $id_back==='' || $selfie===''){
  jerr('required_fields_missing', 400);
}

$st = $mysqli->prepare("SELECT id,status FROM ex_user_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->bind_param("i", $uid);
$st->execute();
$prev = $st->get_result()->fetch_assoc();
if ($prev && in_array($prev['status'], ['pending','approved'], true)){
  echo json_encode(['ok'=>true,'status'=>$prev['status']], JSON_UNESCAPED_UNICODE);
  exit;
}

$nid_hash = password_hash($national_id, PASSWORD_DEFAULT);

$mysqli->query("
  CREATE TABLE IF NOT EXISTS ex_user_kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    national_id_hash VARCHAR(255) NOT NULL,
    dob DATE,
    address TEXT,
    id_front_url VARCHAR(255),
    id_back_url VARCHAR(255),
    selfie_url VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$ins = $mysqli->prepare("
  INSERT INTO ex_user_kyc (user_id, full_name, national_id_hash, dob, address, id_front_url, id_back_url, selfie_url, status, created_at, updated_at)
  VALUES (?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
");
$ins->bind_param("isssssss", $uid, $full_name, $nid_hash, $dob, $address, $id_front, $id_back, $selfie);
$ins->execute();

echo json_encode(['ok'=>true,'status'=>'pending'], JSON_UNESCAPED_UNICODE);
