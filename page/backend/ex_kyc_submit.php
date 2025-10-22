<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$full_name = trim((string)($input['full_name'] ?? ''));
$national_id = preg_replace('/\D+/', '', (string)($input['national_id'] ?? ''));
$dob = trim((string)($input['dob'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$id_front = trim((string)($input['id_front_url'] ?? ''));
$id_back = trim((string)($input['id_back_url'] ?? ''));
$selfie = trim((string)($input['selfie_url'] ?? ''));

if ($full_name==='' || $national_id==='' || $dob==='' || $address==='' || $id_front==='' || $selfie===''){
  jerr('missing_fields');
}
// deny if already pending/approved
$st = $mysqli->prepare("SELECT status FROM ex_user_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->bind_param("i", $uid);
$st->execute();
$prev = $st->get_result()->fetch_assoc();
if ($prev && in_array($prev['status'], ['pending','approved'])){
  echo json_encode(['ok'=>true,'status'=>$prev['status']]); exit;
}

// store a hash of national_id instead of raw (privacy)
$nid_hash = password_hash($national_id, PASSWORD_DEFAULT);

$st = $mysqli->prepare("
  INSERT INTO ex_user_kyc (user_id, full_name, national_id_hash, dob, address, id_front_url, id_back_url, selfie_url, status, created_at, updated_at)
  VALUES (?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
");
$st->bind_param("isssssss", $uid, $full_name, $nid_hash, $dob, $address, $id_front, $id_back, $selfie);
$st->execute();
echo json_encode(['ok'=>true,'status'=>'pending'], JSON_UNESCAPED_UNICODE);
