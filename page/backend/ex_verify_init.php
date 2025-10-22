<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string)($input['email'] ?? ''));
if ($email==='') jerr('email_required');

// rate limit: max 3 pending within 15 minutes
$st = $mysqli->prepare("SELECT COUNT(*) c FROM ex_user_verifications WHERE user_id=? AND verified_at IS NULL AND created_at > (NOW() - INTERVAL 15 MINUTE)");
$st->bind_param("i", $uid);
$st->execute();
$c = (int)$st->get_result()->fetch_assoc()['c'];
if ($c >= 3) jerr('too_many_requests');

$code = random_int(100000, 999999);
$hash = password_hash((string)$code, PASSWORD_DEFAULT);

$st = $mysqli->prepare("
  INSERT INTO ex_user_verifications (user_id, email, code_hash, attempts, created_at, updated_at)
  VALUES (?,?,?,0,NOW(),NOW())
");
$st->bind_param("iss", $uid, $email, $hash);
$st->execute();

// Try to send email (basic mail(); replace with SMTP as needed)
$subject = 'THAMMUE – รหัสยืนยันอีเมลของคุณ';
$body = "รหัสยืนยันของคุณคือ: $code\nรหัสจะหมดอายุใน 15 นาที";
$headers = "Content-Type: text/plain; charset=utf-8\r\n";
@mail($email, $subject, $body, $headers);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
