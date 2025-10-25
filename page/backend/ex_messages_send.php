<?php
require_once __DIR__ . '/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx();
$uid = me();

try {
  if (!$uid) jerr('not_logged_in', 401);

  $room_id = (int)($_POST['room_id'] ?? ($_POST['chat_id'] ?? 0));
  $body    = trim((string)($_POST['body'] ?? ''));
  if ($room_id <= 0 || $body === '') jerr('bad_params');

  $st = $m->prepare("SELECT 1 FROM " . T_CHAT_PARTICIPANTS . " WHERE room_id=? AND user_id=?");
  $st->bind_param("ii", $room_id, $uid);
  $st->execute();
  if (!$st->get_result()->fetch_row()) jerr('forbidden', 403);

  // หาอีกฝั่ง
  $st = $m->prepare("SELECT user_id FROM " . T_CHAT_PARTICIPANTS . " WHERE room_id=? AND user_id<>? LIMIT 1");
  $st->bind_param("ii", $room_id, $uid);
  $st->execute();
  $other = $st->get_result()->fetch_assoc();
  $recipient_id = $other ? (int)$other['user_id'] : null;

  if ($recipient_id) {
    $st = $m->prepare("INSERT INTO " . T_CHAT_MESSAGES . "
      (room_id,sender_id,recipient_id,body,is_system,created_at)
      VALUES (?,?,?,?,0,NOW())");
    $st->bind_param("iiis", $room_id, $uid, $recipient_id, $body);
  } else {
    $st = $m->prepare("INSERT INTO " . T_CHAT_MESSAGES . "
      (room_id,sender_id,body,is_system,created_at)
      VALUES (?,?,?,0,NOW())");
    $st->bind_param("iis", $room_id, $uid, $body);
  }
  $st->execute();

  $m->query("UPDATE " . T_CHAT_ROOMS . " SET updated_at=NOW() WHERE id=" . (int)$room_id);

  if (function_exists('ob_get_length') && ob_get_length()) {
    ob_clean();
  }
  jok(['ok' => true]);
} catch (Throwable $e) {
  jerr('db_error: ' . $e->getMessage(), 500);
}
