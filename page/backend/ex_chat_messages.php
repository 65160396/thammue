<?php
// ข้อความในห้อง
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $m = dbx();
  if (session_status()!==PHP_SESSION_ACTIVE) session_start();
  $uid = me();
  if (!$uid) jerr('not_logged_in',401);

  // ensure messages table
  $m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_MESSAGES."(
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY(room_id, id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $room_id = (int)($_GET['room_id'] ?? 0);
  $since_id= (int)($_GET['since_id'] ?? 0);
  if ($room_id<=0) jerr('bad_room');

  // verify member
  $st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
  $st->bind_param("ii",$room_id,$uid);
  $st->execute();
  if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

  if ($since_id>0){
    $st = $m->prepare("SELECT id, user_id, body, created_at FROM ".T_CHAT_MESSAGES."
                       WHERE room_id=? AND id>? ORDER BY id ASC");
    $st->bind_param("ii",$room_id,$since_id);
  } else {
    $st = $m->prepare("SELECT id, user_id, body, created_at FROM ".T_CHAT_MESSAGES."
                       WHERE room_id=? ORDER BY id ASC LIMIT 500");
    $st->bind_param("i",$room_id);
  }
  $st->execute();
  $rows = stmt_all_assoc($st);

  // mark is_me
  foreach($rows as &$r){ $r['is_me'] = ((int)$r['user_id']===$uid); }

  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
