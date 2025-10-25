<?php
require_once __DIR__.'/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx(); $uid = me();

try {
  if (!$uid) jerr('not_logged_in', 401);
  $room_id = (int)($_GET['room_id'] ?? 0);
  if ($room_id<=0) jerr('missing room_id');

  $st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
  $st->bind_param("ii",$room_id,$uid); $st->execute();
  if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

  $st = $m->prepare("SELECT title FROM ".T_CHAT_ROOMS." WHERE id=? LIMIT 1");
  $st->bind_param("i",$room_id); $st->execute();
  $info = stmt_one_assoc($st);
  if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
  jok(['room_name'=>$info['title'] ?? ('ห้อง #'.$room_id)]);
} catch (Throwable $e) { jerr('db_error: '.$e->getMessage(), 500); }
