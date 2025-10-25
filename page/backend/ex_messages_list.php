<?php
require_once __DIR__.'/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$m = dbx(); $uid = me();

try {
  if (!$uid) jerr('not_logged_in',401);
  $room_id = (int)($_GET['room_id'] ?? ($_GET['chat_id'] ?? 0));
  if ($room_id<=0) jerr('bad_chat');

  $st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
  $st->bind_param("ii",$room_id,$uid); $st->execute();
  if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

  $since_id = (int)($_GET['since_id'] ?? 0);
  if ($since_id>0){
    $st = $m->prepare("SELECT id,sender_id,body,created_at
                       FROM ".T_CHAT_MESSAGES."
                       WHERE room_id=? AND id>? ORDER BY id ASC");
    $st->bind_param("ii",$room_id,$since_id);
  }else{
    $st = $m->prepare("SELECT id,sender_id,body,created_at
                       FROM ".T_CHAT_MESSAGES."
                       WHERE room_id=? ORDER BY id ASC LIMIT 500");
    $st->bind_param("i",$room_id);
  }
  $st->execute();
  $rows = stmt_all_assoc($st);
  foreach($rows as &$r){ $r['is_mine'] = ((int)$r['sender_id'] === $uid); }

  if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
  // หลังตรวจสิทธิ์เรียบร้อย ให้ mark เป็นอ่าน
$mk = $m->prepare("UPDATE ".T_CHAT_MESSAGES." SET is_read=1 WHERE room_id=? AND recipient_id=? AND is_read=0");
$mk->bind_param("ii", $room_id, $uid);
$mk->execute();

  jok(['items'=>$rows]);
} catch (Throwable $e) { jerr('db_error: '.$e->getMessage(), 500); }
