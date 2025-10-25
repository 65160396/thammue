<?php
// ส่งข้อความ
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $m = dbx();
  if (session_status()!==PHP_SESSION_ACTIVE) session_start();
  $uid = me();
  if (!$uid) jerr('not_logged_in',401);

  // ensure tables
  $m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_ROOMS."(
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NULL,
    title VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_PARTICIPANTS."(
    room_id INT NOT NULL, user_id INT NOT NULL,
    PRIMARY KEY(room_id,user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_MESSAGES."(
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL, user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY(room_id,id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $room_id = (int)($_POST['room_id'] ?? 0);
  $body    = trim((string)($_POST['body'] ?? ''));
  if ($room_id<=0 || $body==='') jerr('bad_params');

  // verify member
  $st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
  $st->bind_param("ii",$room_id,$uid);
  $st->execute();
  if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

  // insert
  $ins = $m->prepare("INSERT INTO ".T_CHAT_MESSAGES."(room_id,user_id,body,created_at) VALUES (?,?,?,NOW())");
  $ins->bind_param("iis",$room_id,$uid,$body);
  $ins->execute();

  // touch room
  $m->query("UPDATE ".T_CHAT_ROOMS." SET updated_at=NOW() WHERE id=".$room_id);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
