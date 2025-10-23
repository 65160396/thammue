<?php
// /page/backend/ex_item_report.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$item_id = (int)($_POST['item_id'] ?? 0);
$reason  = trim((string)($_POST['reason'] ?? ''));

if ($item_id <= 0) jerr('bad_id', 400);
if ($reason === '') jerr('reason_required', 400);
if (mb_strlen($reason) > 1000) $reason = mb_substr($reason, 0, 1000);

// ตรวจว่ามีสินค้านี้จริง
$table = EX_ITEMS_TABLE;
$st0 = $mysqli->prepare("SELECT id FROM `$table` WHERE id=?");
$st0->bind_param('i', $item_id);
$st0->execute();
if (!$st0->get_result()->fetch_row()) jerr('not_found', 404);

// ตารางเก็บรีพอร์ต แนะนำให้สร้างดังนี้ (ครั้งเดียว):
/*
CREATE TABLE IF NOT EXISTS ex_item_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (item_id),
  INDEX (reporter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

$st = $mysqli->prepare("INSERT INTO ex_item_reports (item_id, reporter_id, reason, created_at)
                        VALUES (?, ?, ?, NOW())");
$st->bind_param('iis', $item_id, $uid, $reason);
if (!$st->execute()) jerr('db_insert_failed', 500);

echo json_encode(['ok'=>true, 'id'=>$mysqli->insert_id], JSON_UNESCAPED_UNICODE);
