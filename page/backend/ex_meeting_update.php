<?php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$rid = (int)($in['request_id'] ?? 0);
$scheduled_at = trim((string)($in['scheduled_at'] ?? ''));
$place = trim((string)($in['place'] ?? ''));
$note  = trim((string)($in['note'] ?? ''));
if ($rid<=0) jerr('bad_request', 400);

$st = $m->prepare("
  SELECT it_req.user_id AS owner_user_id, it_off.user_id AS requester_user_id
  FROM ".T_REQUESTS." r
  JOIN ".T_ITEMS." it_req ON it_req.id = r.requested_item_id
  JOIN ".T_ITEMS." it_off ON it_off.id = r.offered_item_id
  WHERE r.id=? LIMIT 1
");
$st->bind_param("i", $rid);
$st->execute();
$rrow = stmt_one_assoc($st);
if (!$rrow) jerr('not_found', 404);
if ($uid!==(int)$rrow['owner_user_id'] && $uid!==(int)$rrow['requester_user_id']) jerr('forbidden', 403);

/* upsert ex_meetings table */
$m->query("CREATE TABLE IF NOT EXISTS ex_meetings (
  request_id INT PRIMARY KEY,
  scheduled_at DATETIME NULL,
  place VARCHAR(255) NULL,
  note TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$st2 = $m->prepare("
  INSERT INTO ex_meetings (request_id, scheduled_at, place, note, updated_at)
  VALUES (?, ?, ?, ?, NOW())
  ON DUPLICATE KEY UPDATE scheduled_at=VALUES(scheduled_at), place=VALUES(place), note=VALUES(note), updated_at=NOW()
");
$sa = ($scheduled_at !== '') ? $scheduled_at : null;
$st2->bind_param("isss", $rid, $sa, $place, $note);
$st2->execute();

/* แจ้งอีกฝ่าย */
$other = ($uid===(int)$rrow['owner_user_id']) ? (int)$rrow['requester_user_id'] : (int)$rrow['owner_user_id'];
$typ='meeting_updated'; $title='มีการอัปเดตนัดหมาย'; $body='มีการอัปเดตเวลา/สถานที่นัดหมายเพื่อแลกเปลี่ยน';
$ins = $m->prepare("INSERT INTO ".T_NOTIFICATIONS." (user_id,type,ref_id,title,body,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
$ins->bind_param("isiss", $other, $typ, $rid, $title, $body);
$ins->execute();

jok();
