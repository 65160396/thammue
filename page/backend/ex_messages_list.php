<?php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in',401);

// รองรับทั้ง room_id และ (เดิม) chat_id
$room_id = (int)($_GET['room_id'] ?? ($_GET['chat_id'] ?? 0));
if ($room_id<=0) jerr('bad_chat');

// ต้องเป็นสมาชิกห้อง
$st = $m->prepare("SELECT 1 FROM ex_chat_participants WHERE room_id=? AND user_id=?");
$st->bind_param("ii",$room_id,$uid);
$st->execute();
if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

$since_id = (int)($_GET['since_id'] ?? 0);
if ($since_id>0){
  $st = $m->prepare("SELECT id, user_id AS sender_id, body, created_at
                     FROM ex_chat_messages
                     WHERE room_id=? AND id>?
                     ORDER BY id ASC");
  $st->bind_param("ii",$room_id,$since_id);
} else {
  $st = $m->prepare("SELECT id, user_id AS sender_id, body, created_at
                     FROM ex_chat_messages
                     WHERE room_id=?
                     ORDER BY id ASC LIMIT 500");
  $st->bind_param("i",$room_id);
}
$st->execute();
$rows = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
