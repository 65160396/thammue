<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// Return current verification status and known email (from users table if present)
$email = '';
try {
  // Try fetch email from users
  $st = $mysqli->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
  $st->bind_param("i", $uid);
  $st->execute();
  if ($row = $st->get_result()->fetch_assoc()) { $email = (string)$row['email']; }
} catch (Exception $e){ /* ignore */ }

$st = $mysqli->prepare("SELECT verified_at, email FROM ex_user_verifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->bind_param("i", $uid);
$st->execute();
$v = $st->get_result()->fetch_assoc();

$verified = false; $known = $email;
if ($v){
  $verified = !empty($v['verified_at']);
  if (!$known && !empty($v['email'])) $known = $v['email'];
}
echo json_encode(['ok'=>true,'verified'=>$verified,'email'=>$known], JSON_UNESCAPED_UNICODE);
