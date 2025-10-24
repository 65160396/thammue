<?php
// /page/backend/ex_meetings_list.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

/* ตารางถ้ามี (safe create) */
$m->query("CREATE TABLE IF NOT EXISTS ex_meetings (
  request_id INT PRIMARY KEY,
  scheduled_at DATETIME NULL,
  place VARCHAR(255) NULL,
  note TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* นัดหมายของคำขอที่เกี่ยวข้องกับเรา */
$st = $m->prepare("
  SELECT m.request_id, m.scheduled_at, m.place, m.note, m.updated_at,
         r.status,
         it_req.title AS req_title, it_off.title AS off_title
  FROM ex_meetings m
  JOIN ".T_REQUESTS." r ON r.id = m.request_id
  JOIN ".T_ITEMS." it_req ON it_req.id = r.requested_item_id
  JOIN ".T_ITEMS." it_off ON it_off.id = r.offered_item_id
  WHERE it_req.user_id=? OR it_off.user_id=?
  ORDER BY m.updated_at DESC
");
$st->bind_param("ii", $uid, $uid);
$st->execute();
$data = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'meetings'=>$data], JSON_UNESCAPED_UNICODE);
