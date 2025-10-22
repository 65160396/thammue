<?php
// /exchangepage/api/notifications/mark_read.php
require_once __DIR__ . '/_guard.php';
$pdo = db();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$ids = $data['ids'] ?? [];     // [1,2,3] ถ้าว่าง = ทำทั้งหมด
$ids = array_values(array_filter(array_map('intval', (array)$ids)));

if (!$ids) {
  $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
  $stmt->execute([me()]);
} else {
  $in = implode(',', array_fill(0, count($ids), '?'));
  array_unshift($ids, me());
  $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($in)");
  $stmt->execute($ids);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
