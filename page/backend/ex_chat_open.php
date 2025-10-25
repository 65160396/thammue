<?php
// เปิด/สร้างห้องแชท 1:1 โดยผูกกับสินค้า และตั้งชื่อห้อง = ชื่อสินค้า
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// ===== รับพารามิเตอร์ =====
$other    = (int)($_POST['other_user_id'] ?? 0);
$item_id  = (int)($_POST['item_id'] ?? 0);
if ($other <= 0 || $other === $uid) jerr('bad_user', 400);

// ===== ตารางที่ต้องใช้ =====
$m->query("CREATE TABLE IF NOT EXISTS ex_chat_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NULL,
  title VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP,
  KEY(item_id), KEY(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$m->query("CREATE TABLE IF NOT EXISTS ex_chat_participants (
  room_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY(room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$m->query("CREATE TABLE IF NOT EXISTS ex_chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY(room_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ===== หา title จากสินค้า (ถ้าไม่ได้ส่ง item_id มาก็ปล่อยให้ว่าง) =====
$title = null;
if ($item_id > 0) {
  // ดึงชื่อสินค้าจากตารางสินค้า (แก้ชื่อ table ได้ที่ ex__common.php → T_ITEMS)
  $st = $m->prepare("SELECT title FROM ".T_ITEMS." WHERE id=? LIMIT 1");
  $st->bind_param('i', $item_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $title = $row['title'] ?? ('Item#'.$item_id);
}

// ===== หา “ห้องเดิม” ของคู่สนทนานี้ (ล็อกที่ item_id เดียวกันด้วย) =====
$st = $m->prepare("
  SELECT r.id
  FROM ex_chat_rooms r
  JOIN ex_chat_participants p1 ON p1.room_id=r.id AND p1.user_id=?
  JOIN ex_chat_participants p2 ON p2.room_id=r.id AND p2.user_id=?
  WHERE (r.item_id <=> ?)          -- match ทั้ง = และ NULL
  LIMIT 1
");
$nullable_item = ($item_id ?: null);
$st->bind_param('iii', $uid, $other, $nullable_item);
$st->execute();
$found = $st->get_result()->fetch_assoc();

if ($found) {
  echo json_encode(['ok'=>true,'room_id'=>(int)$found['id']], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== สร้างห้องใหม่ (ตั้งชื่อห้อง = ชื่อสินค้า) =====
$m->begin_transaction();
try {
  $st = $m->prepare("INSERT INTO ex_chat_rooms (item_id, title, updated_at) VALUES (?,?,NOW())");
  $st->bind_param('is', $item_id, $title);
  $st->execute();
  $room_id = (int)$m->insert_id;

  $st2 = $m->prepare("INSERT INTO ex_chat_participants (room_id, user_id) VALUES (?,?),(?,?)");
  $st2->bind_param('iiii', $room_id, $uid, $room_id, $other);
  $st2->execute();

  $m->commit();
  echo json_encode(['ok'=>true,'room_id'=>$room_id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $m->rollback();
  echo json_encode(['ok'=>false,'error'=>'fatal: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
