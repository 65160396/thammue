<?php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

/* pending requests ที่คนอื่นขอ “ของเรา” */
$st = $m->prepare("
  SELECT COUNT(*)
  FROM ex_requests r
  JOIN ex_items it_req ON it_req.id = r.requested_item_id
  WHERE it_req.user_id=? AND r.status='pending'
");
$st->bind_param("i", $uid);
$st->execute();
$pending = (int)$st->get_result()->fetch_row()[0];

/* notifications unread (คงเดิม) */
$m->query("CREATE TABLE IF NOT EXISTS ex_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, type VARCHAR(50) NOT NULL, ref_id INT NULL,
  title VARCHAR(255) NOT NULL, body TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY(user_id), KEY(type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$st2 = $m->prepare("SELECT COUNT(*) FROM ex_notifications WHERE user_id=? AND is_read=0");
$st2->bind_param("i", $uid); $st2->execute();
$unread = (int)$st2->get_result()->fetch_row()[0];

jok(['pending_requests'=>$pending,'unread_notifications'=>$unread]);
