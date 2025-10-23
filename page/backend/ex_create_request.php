<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$requested_item_id = (int)($input['requested_item_id'] ?? 0);
$offered_item_id   = (int)($input['offered_item_id'] ?? 0);
$message           = trim((string)($input['message'] ?? ''));

if ($requested_item_id<=0 || $offered_item_id<=0) jerr('bad_request');

$st = $mysqli->prepare("SELECT id,user_id FROM items WHERE id=? LIMIT 1");
$st->bind_param("i", $requested_item_id);
$st->execute();
$reqItem = $st->get_result()->fetch_assoc();
if (!$reqItem) jerr('requested_item_not_found', 404);
$owner_user_id = (int)$reqItem['user_id'];
if ($owner_user_id === $uid) jerr('cannot_request_own_item');

$st = $mysqli->prepare("SELECT id,user_id FROM items WHERE id=? LIMIT 1");
$st->bind_param("i", $offered_item_id);
$st->execute();
$offItem = $st->get_result()->fetch_assoc();
if (!$offItem) jerr('offered_item_not_found', 404);
if ((int)$offItem['user_id'] !== $uid) jerr('offered_item_not_owned');

$st = $mysqli->prepare("
  INSERT INTO ex_requests (requested_item_id, offered_item_id, requester_user_id, owner_user_id, status, message, created_at, updated_at)
  VALUES (?,?,?,?, 'pending', ?, NOW(), NOW())
");
$st->bind_param("iiiis", $requested_item_id, $offered_item_id, $uid, $owner_user_id, $message);
$st->execute();
$rid = $mysqli->insert_id;

$typ='request'; $title='มีคำขอแลกเปลี่ยนใหม่'; $body='มีผู้ใช้ส่งคำขอแลกสินค้ากับคุณ';
$st = $mysqli->prepare("INSERT INTO ex_notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?,?,?,?,?,0,NOW())");
$st->bind_param("isiss", $owner_user_id, $typ, $rid, $title, $body);
$st->execute();

jok(['request_id'=>$rid]);
// ... ดึง $targetOwnerId จาก ex_items target_item_id ก่อน
if ((int)$targetOwnerId === (int)$uid) {
  jerr('cannot_offer_to_own_item', 400);
}

