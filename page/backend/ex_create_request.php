<?php
// /page/backend/ex_create_request.php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$requested_item_id = (int)($input['requested_item_id'] ?? 0);
$offered_item_id   = (int)($input['offered_item_id'] ?? 0);
$message           = trim((string)($input['message'] ?? ''));

if ($requested_item_id<=0 || $offered_item_id<=0) jerr('bad_request', 400);

/* หา owner/requester จาก ex_items */
$st = $mysqli->prepare("SELECT id,user_id FROM ".T_ITEMS." WHERE id=? LIMIT 1");
$st->bind_param("i", $requested_item_id);
$st->execute(); $reqIt = stmt_one_assoc($st);
if (!$reqIt) jerr('requested_item_not_found', 404);
$owner_id = (int)$reqIt['user_id'];
if ($owner_id === $uid) jerr('cannot_request_own_item', 400);

$st = $mysqli->prepare("SELECT id,user_id FROM ".T_ITEMS." WHERE id=? LIMIT 1");
$st->bind_param("i", $offered_item_id);
$st->execute(); $offIt = stmt_one_assoc($st);
if (!$offIt) jerr('offered_item_not_found', 404);
if ((int)$offIt['user_id'] !== $uid) jerr('not_owner_of_offered_item', 403);

/* สร้างตาราง ex_requests ถ้ายังไม่มี */
$mysqli->query("CREATE TABLE IF NOT EXISTS ".T_REQUESTS." (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requested_item_id INT NOT NULL,
  offered_item_id INT NOT NULL,
  status ENUM('pending','accepted','declined','canceled') NOT NULL DEFAULT 'pending',
  message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (requested_item_id), KEY (offered_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* duplicate guard */
$st = $mysqli->prepare("SELECT id FROM ".T_REQUESTS." WHERE requested_item_id=? AND offered_item_id=? AND status='pending' LIMIT 1");
$st->bind_param("ii", $requested_item_id, $offered_item_id);
$st->execute();
if ($st->get_result()->fetch_row()) jerr('duplicate_pending', 409);

/* insert */
$st = $mysqli->prepare("INSERT INTO ".T_REQUESTS." (requested_item_id,offered_item_id,status,message) VALUES (?,?, 'pending', ?)");
$st->bind_param("iis", $requested_item_id, $offered_item_id, $message);
$st->execute();
$rid = (int)$mysqli->insert_id;

/* notifications table safe-create */
$mysqli->query("CREATE TABLE IF NOT EXISTS ".T_NOTIFICATIONS." (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, type VARCHAR(50) NOT NULL, ref_id INT NULL,
  title VARCHAR(255) NOT NULL, body TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$typ = 'request_created'; $title = 'มีคำขอแลกใหม่'; $body  = 'มีผู้ใช้ส่งคำขอแลกมายังสินค้าของคุณ';
$st = $mysqli->prepare("INSERT INTO ".T_NOTIFICATIONS." (user_id,type,ref_id,title,body,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
$st->bind_param("isiss", $owner_id, $typ, $rid, $title, $body);
$st->execute();

jok(['request_id'=>$rid]);
