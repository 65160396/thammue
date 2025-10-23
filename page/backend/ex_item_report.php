<?php
// ทุกคนส่งรีพอร์ตได้ (จะบันทึกไว้ให้แอดมินตรวจ)
$REQUIRE_LOGIN = false;
require_once __DIR__ . '/ex__items_common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$itemId = (int)($_POST['item_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
if ($itemId <= 0 || $reason === '') jerr('bad_request');

$repoTable = 'ex_item_reports';

// สร้างตารางถ้ายังไม่มี (ง่าย ๆ)
$mysqli->query("
  CREATE TABLE IF NOT EXISTS `$repoTable` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$st = $mysqli->prepare("INSERT INTO `$repoTable` (item_id,user_id,reason) VALUES (?,?,?)");
$uidOrNull = $uid ? (int)$uid : null;
$st->bind_param('iis', $itemId, $uidOrNull, $reason);
$ok = $st->execute();

echo json_encode(['ok'=> (bool)$ok]);
