<?php
// /page/backend/ex_messages_list.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in',401);

$chat_id = (int)($_GET['chat_id'] ?? 0);
if ($chat_id<=0) jerr('bad_chat');

$st = $m->prepare("SELECT a_user_id,b_user_id FROM ex_chats WHERE id=?");
$st->bind_param("i",$chat_id);
$st->execute();
$ch = $st->get_result()->fetch_assoc();
if (!$ch || ($uid!=$ch['a_user_id'] && $uid!=$ch['b_user_id'])) jerr('forbidden',403);

$since_id = (int)($_GET['since_id'] ?? 0);
if ($since_id>0){
  $st = $m->prepare("SELECT id, sender_id, body, created_at FROM ex_messages WHERE chat_id=? AND id>? ORDER BY id ASC");
  $st->bind_param("ii",$chat_id,$since_id);
} else {
  $st = $m->prepare("SELECT id, sender_id, body, created_at FROM ex_messages WHERE chat_id=? ORDER BY id ASC LIMIT 200");
  $st->bind_param("i",$chat_id);
}
$st->execute();
$rows = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
