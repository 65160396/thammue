<?php
// api/notifications/mark_read.php
require_once __DIR__ . '/_guard.php';
$pdo = db();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$ids = $data['ids'] ?? [];     // [1,2,3] ถ้าว่าง = ทำทั้งหมด
$markAll = empty($ids);

if ($markAll) {
  $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
  $stmt->execute([me()]);
} else {
  // กันยิง id ของคนอื่น
  $in = implode(',', array_fill(0, count($ids), '?'));
  $params = array_map('intval', $ids);
  array_unshift($params, me());
  $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($in)");
  $stmt->execute($params);
}

echo json_encode(['ok' => true]);
