<?php
// /page/backend/ex_item_delete.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$item_id = (int)($_POST['item_id'] ?? 0);
if ($item_id <= 0) jerr('bad_id', 400);

$table = EX_ITEMS_TABLE;

// ตรวจว่าเป็นของตัวเอง
$st = $mysqli->prepare("SELECT user_id, thumbnail_url FROM `$table` WHERE id=?");
$st->bind_param('i', $item_id);
$st->execute();
$r = $st->get_result()->fetch_assoc();
if (!$r) jerr('not_found', 404);
if ((int)$r['user_id'] !== (int)$uid) jerr('forbidden', 403);

// ลบ DB (ไฟล์รูปถ้าจะลบจริงให้เพิ่ม unlink ตาม path /uploads/*)
$st2 = $mysqli->prepare("DELETE FROM `$table` WHERE id=?");
$st2->bind_param('i', $item_id);
$ok = $st2->execute();
if (!$ok) jerr('db_delete_failed', 500);

echo json_encode(['ok'=>true, 'deleted_id'=>$item_id], JSON_UNESCAPED_UNICODE);
