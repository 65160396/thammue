<?php
// /page/backend/ex_request_create.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php'; // มี $mysqli, $uid, jerr(), jok() และเชื่อม DB shopdb_ex

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// สินค้าของ “เขา” ที่เราจะไปขอแลก
$target_item_id = (int)($_POST['target_item_id'] ?? 0);
// สินค้าของ “เรา” ที่จะเสนอแลก
$offer_item_id  = (int)($_POST['offer_item_id']  ?? 0);
// โน้ตเพิ่มเติมจากผู้ขอ (ไม่บังคับ)
$note           = trim((string)($_POST['note'] ?? ''));

if ($target_item_id <= 0 || $offer_item_id <= 0) jerr('bad_params', 400);
if ($target_item_id === $offer_item_id) jerr('same_item', 400);

// อ่านข้อมูลเจ้าของของทั้งสองชิ้น
$st = $mysqli->prepare("SELECT id, user_id FROM ex_items WHERE id IN (?, ?)");
$st->bind_param("ii", $target_item_id, $offer_item_id);
$st->execute();
$rows = stmt_all_assoc($st);

$owner_of_target = null;
$owner_of_offer  = null;
foreach ($rows as $r) {
  if ((int)$r['id'] === $target_item_id) $owner_of_target = (int)$r['user_id'];
  if ((int)$r['id'] === $offer_item_id)  $owner_of_offer  = (int)$r['user_id'];
}

if (!$owner_of_target || !$owner_of_offer) jerr('item_not_found', 404);
if ($owner_of_target === $uid) jerr('cannot_request_own_item', 403); // ห้ามขอแลกของตัวเอง
if ($owner_of_offer  !== $uid) jerr('offer_item_not_owned', 403);     // สินค้าที่เสนอไม่ใช่ของเรา

// กันคำขอซ้ำที่ยังค้างสถานะ pending
$st = $mysqli->prepare("
  SELECT id FROM ex_requests
  WHERE requester_user_id=? AND owner_user_id=? 
    AND requested_item_id=? AND offered_item_id=?
    AND status='pending'
  LIMIT 1
");
$st->bind_param("iiii", $uid, $owner_of_target, $target_item_id, $offer_item_id);
$st->execute();
if ($st->get_result()->fetch_row()) jerr('duplicate_pending', 409);

// บันทึกคำขอแลกเปลี่ยน
$st = $mysqli->prepare("
  INSERT INTO ex_requests
    (requester_user_id, owner_user_id, requested_item_id, offered_item_id, note, status, created_at, updated_at)
  VALUES
    (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
");
$st->bind_param("iiiis", $uid, $owner_of_target, $target_item_id, $offer_item_id, $note);
if (!$st->execute()) jerr('db_insert_fail', 500);

$request_id = $mysqli->insert_id;

// แจ้งเตือนเจ้าของสินค้า
$title = 'มีคำขอแลกสินค้าใหม่';
$body  = 'มีผู้ใช้ส่งคำขอแลกเปลี่ยนสินค้าของคุณ';
$typ   = 'request_new';
$st = $mysqli->prepare("
  INSERT INTO ex_notifications (user_id, type, ref_id, title, body, is_read, created_at)
  VALUES (?, ?, ?, ?, ?, 0, NOW())
");
$st->bind_param("isiss", $owner_of_target, $typ, $request_id, $title, $body);
$st->execute();

jok(['request_id' => $request_id]);
