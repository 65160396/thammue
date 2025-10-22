<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// find latest pending
$st = $mysqli->prepare("
  SELECT id, email, created_at FROM ex_user_verifications
  WHERE user_id=? AND verified_at IS NULL
  ORDER BY id DESC LIMIT 1
");
$st->bind_param("i", $uid);
$st->execute();
$v = $st->get_result()->fetch_assoc();
if (!$v) jerr('no_pending', 404);

// throttle: at least 60 sec between resends
$st2 = $mysqli->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as secs");
$st2->bind_param("s", $v['created_at']);
$st2->execute();
$secs = (int)$st2->get_result()->fetch_assoc()['secs'];
if ($secs < 60) jerr('too_fast');

$code = random_int(100000, 999999);
$hash = password_hash((string)$code, PASSWORD_DEFAULT);

$u = $mysqli->prepare("UPDATE ex_user_verifications SET code_hash=?, created_at=NOW(), updated_at=NOW() WHERE id=?");
$u->bind_param("si", $hash, $v['id']);
$u->execute();

$subject = 'THAMMUE – รหัสยืนยันอีเมล (ส่งใหม่)';
$body = "รหัสยืนยันของคุณคือ: $code\nรหัสจะหมดอายุใน 15 นาที";
$headers = "Content-Type: text/plain; charset=utf-8\r\n";
@mail($v['email'], $subject, $body, $headers);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
