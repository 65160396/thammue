<?php
// /page/backend/ex_chat_open.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$other = (int)($_POST['other_user_id'] ?? 0);
if ($other<=0 || $other===$uid) jerr('bad_user', 400);

/* create chat schema if missing */
$m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_ROOMS." (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_PARTICIPANTS." (
  room_id INT NOT NULL, user_id INT NOT NULL,
  PRIMARY KEY (room_id,user_id), KEY(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$m->query("CREATE TABLE IF NOT EXISTS ".T_CHAT_MESSAGES." (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL, user_id INT NOT NULL, body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY(room_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* find or create 1:1 room */
$st = $m->prepare("
  SELECT r.id
  FROM ".T_CHAT_ROOMS." r
  JOIN ".T_CHAT_PARTICIPANTS." p1 ON p1.room_id=r.id AND p1.user_id=?
  JOIN ".T_CHAT_PARTICIPANTS." p2 ON p2.room_id=r.id AND p2.user_id=?
  LIMIT 1
");
$st->bind_param("ii", $uid, $other);
$st->execute();
$row = $st->get_result()->fetch_assoc();

if (!$row){
  $m->begin_transaction();
  $m->query("INSERT INTO ".T_CHAT_ROOMS." (title,updated_at) VALUES (NULL,NOW())");
  $room_id = (int)$m->insert_id;
  $ins = $m->prepare("INSERT INTO ".T_CHAT_PARTICIPANTS." (room_id,user_id) VALUES (?,?),(?,?)");
  $ins->bind_param("iiii", $room_id, $uid, $room_id, $other);
  $ins->execute();
  $m->commit();
} else {
  $room_id = (int)$row['id'];
}

echo json_encode(['ok'=>true,'room_id'=>$room_id], JSON_UNESCAPED_UNICODE);
