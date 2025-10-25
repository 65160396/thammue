<?php
require_once __DIR__.'/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx(); $uid = me();

try {
  if (!$uid) jerr('not_logged_in', 401);

  $sql = "
    SELECT
      r.id,
      r.title AS room_name,
      r.updated_at,
      (SELECT body FROM ".T_CHAT_MESSAGES." m
         WHERE m.room_id=r.id ORDER BY m.id DESC LIMIT 1) AS last_body
    FROM ".T_CHAT_ROOMS." r
    JOIN ".T_CHAT_PARTICIPANTS." p ON p.room_id=r.id
    WHERE p.user_id=?
    ORDER BY r.updated_at DESC, r.id DESC
  ";
  $st = $m->prepare($sql);
  $st->bind_param("i", $uid);
  $st->execute();
  $rows = stmt_all_assoc($st);

  if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
  jok(['items'=>$rows]);
} catch (Throwable $e) { jerr('db_error: '.$e->getMessage(), 500); }
