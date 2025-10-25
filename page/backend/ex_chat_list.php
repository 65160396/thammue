<?php
// รายการห้องของฉัน (ชื่อห้อง = ชื่อสินค้า)
// ถ้าในห้องมี title อยู่แล้ว จะใช้ title นั้นก่อน จากนั้นค่อย fallback เป็นชื่อสินค้าหรือ Item#<id>
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// ป้องกันกรณีตารางยังไม่ถูกสร้าง
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
  KEY(room_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ชื่อห้อง = COALESCE(ชื่อห้องที่ตั้งไว้, ชื่อสินค้า, 'Item#<id>')
$sql = "
  SELECT
    r.id,
    COALESCE(r.title, i.title, CONCAT('Item#', r.item_id)) AS title,
    r.updated_at
  FROM ex_chat_rooms r
  JOIN ex_chat_participants p ON p.room_id = r.id
  LEFT JOIN ".T_ITEMS." i ON i.id = r.item_id
  WHERE p.user_id = ?
  ORDER BY r.updated_at DESC
  LIMIT 200
";
$st = $m->prepare($sql);
$st->bind_param('i', $uid);
$st->execute();
$rooms = [];
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) {
  $rooms[] = [
    'id'         => (int)$row['id'],
    'title'      => $row['title'] ?? 'ห้องสนทนา',
    'updated_at' => $row['updated_at'],
  ];
}

echo json_encode(['ok'=>true,'rooms'=>$rooms], JSON_UNESCAPED_UNICODE);
