<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$code = trim((string)($input['code'] ?? ''));
if ($code==='' || strlen($code)!==6) jerr('invalid_code');

// get latest pending
$st = $mysqli->prepare("
  SELECT id, code_hash, created_at, email FROM ex_user_verifications
  WHERE user_id=? AND verified_at IS NULL
  ORDER BY id DESC LIMIT 1
");
$st->bind_param("i", $uid);
$st->execute();
$v = $st->get_result()->fetch_assoc();
if (!$v) jerr('no_pending', 404);

// expiry 15 min
$st2 = $mysqli->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) as mins");
$st2->bind_param("s", $v['created_at']);
$st2->execute();
$mins = (int)$st2->get_result()->fetch_assoc()['mins'];
if ($mins > 15) jerr('code_expired', 410);

// verify
if (!password_verify($code, $v['code_hash'])){
  // bump attempts
  $u = $mysqli->prepare("UPDATE ex_user_verifications SET attempts=attempts+1, updated_at=NOW() WHERE id=?");
  $u->bind_param("i", $v['id']); $u->execute();
  jerr('code_mismatch', 403);
}

// mark verified
$u = $mysqli->prepare("UPDATE ex_user_verifications SET verified_at=NOW(), updated_at=NOW() WHERE id=?");
$u->bind_param("i", $v['id']); $u->execute();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
