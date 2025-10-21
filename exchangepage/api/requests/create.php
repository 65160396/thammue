<?php
require __DIR__ . '/../_config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if(!$uid) json_err('AUTH', 401);

$itemId   = (int)($_POST['item_id'] ?? 0);                // ไอเท็มเป้าหมาย (ของเขา)
$reqItemId= (int)($_POST['requester_item_id'] ?? ($_POST['offer_item_id'] ?? 0));
   // ✅ ไอเท็มของผู้ขอ (ของเรา)
$message  = trim((string)($_POST['message'] ?? ''));

if ($itemId <= 0) json_err('BAD_ITEM', 400);

/* ตรวจไอเท็มเป้าหมาย (ของเขา) */
$st = $pdo->prepare("SELECT id, user_id AS owner_id, visibility FROM items WHERE id=:id LIMIT 1");
$st->execute([':id'=>$itemId]);
$item = $st->fetch();
if (!$item) json_err('NOT_FOUND', 404);
if (!in_array($item['visibility'], ['public','pending'], true)) json_err('NOT_ACCEPTING', 403);
if ((int)$item['owner_id'] === $uid) json_err('SELF', 403);

/* (ถ้ามี) ตรวจไอเท็มของผู้ขอให้ถูกต้อง: ต้องเป็นของ user นี้ และอยู่ในสถานะรับแลก */
if ($reqItemId > 0) {
  $chk = $pdo->prepare("SELECT id, user_id, visibility FROM items WHERE id=:i LIMIT 1");
  $chk->execute([':i'=>$reqItemId]);
  $mine = $chk->fetch();

  if (!$mine) json_err('REQUESTER_ITEM_NOT_FOUND', 400);
  if ((int)$mine['user_id'] !== $uid) json_err('REQUESTER_ITEM_NOT_OWNED', 403);
  if (!in_array($mine['visibility'], ['public','pending'], true)) json_err('REQUESTER_ITEM_NOT_ACCEPTING', 403);
}

/* กันคำขอซ้ำ (รายการเดียวกันจากผู้ใช้คนเดิมที่ยัง pending) */
$du = $pdo->prepare("SELECT id FROM requests WHERE item_id=:i AND requester_user_id=:u AND status='pending' LIMIT 1");
$du->execute([':i'=>$itemId, ':u'=>$uid]);
if ($du->fetch()) json_err('EXISTS', 200);

/* บันทึกคำขอ (เก็บ requester_item_id ด้วย) */
$pdo->prepare("
  INSERT INTO requests (item_id, requester_user_id, requester_item_id, message, status, created_at)
  VALUES (:i, :u, :ri, :m, 'pending', NOW())
")->execute([
  ':i'  => $itemId,
  ':u'  => $uid,
  ':ri' => $reqItemId ?: null,   // ✅ อนุญาตให้ว่างได้ ถ้ายังไม่ได้เลือก
  ':m'  => $message
]);

json_ok(['flash' => 'ส่งคำขอแลกเรียบร้อยแล้ว']);
