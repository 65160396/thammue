<?php
// /page/backend/ex_chat_history.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$room_id = (int)($_GET['room_id'] ?? 0);
if ($room_id<=0) jerr('bad_request', 400);

/* permission */
$st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
$st->bind_param("ii", $room_id, $uid);
$st->execute();
if (!$st->get_result()->fetch_row()) jerr('forbidden', 403);

/* messages */
$st = $m->prepare("
  SELECT id, user_id, body, created_at, (user_id=?) AS is_me
  FROM ".T_CHAT_MESSAGES."
  WHERE room_id=?
  ORDER BY id ASC
  LIMIT 500
");
$st->bind_param("ii", $uid, $room_id);
$st->execute();
$messages = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'messages'=>$messages], JSON_UNESCAPED_UNICODE);
