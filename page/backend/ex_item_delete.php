<?php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$itemId = (int)($_POST['item_id'] ?? 0);
if ($itemId <= 0) jerr('bad_item');

$table = EX_ITEMS_TABLE;

// ตรวจว่าเป็นของตัวเอง
$st = $mysqli->prepare("SELECT user_id FROM `$table` WHERE id=?");
$st->bind_param('i', $itemId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
if (!$row) jerr('not_found', 404);
if ((int)$row['user_id'] !== (int)$uid) jerr('forbidden', 403);

// ลบ
$st2 = $mysqli->prepare("DELETE FROM `$table` WHERE id=? LIMIT 1");
$st2->bind_param('i', $itemId);
$ok = $st2->execute();
echo json_encode(['ok'=> (bool)$ok]);
