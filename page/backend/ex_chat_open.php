<?php
// /page/backend/ex_chats_open.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();

if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$other = (int)($_POST['other_user_id'] ?? 0);
if ($other<=0 || $other===$uid) jerr('bad_user');

$a = min($uid, $other);
$b = max($uid, $other);

$st = $m->prepare("SELECT id FROM ex_chats WHERE a_user_id=? AND b_user_id=? LIMIT 1");
$st->bind_param("ii",$a,$b);
$st->execute();
$id = $st->get_result()->fetch_column();

if (!$id){
  $st = $m->prepare("INSERT INTO ex_chats (a_user_id,b_user_id,last_message_at) VALUES (?,?,NOW())");
  $st->bind_param("ii",$a,$b);
  $st->execute();
  $id = $m->insert_id;
}

echo json_encode(['ok'=>true,'chat_id'=>(int)$id], JSON_UNESCAPED_UNICODE);
