<?php
// /thammue/api/chat/open.php
header('Content-Type: application/json');
require __DIR__ . '/../_config.php';

try {
  $pdo = db();
  $uid = (int) me_id();
  $itemId = (int) ($_GET['with_owner_of'] ?? 0);

  if (!$uid)   { echo json_encode(['ok'=>false,'msg'=>'not_login']); exit; }
  if (!$itemId){ echo json_encode(['ok'=>false,'msg'=>'bad_req']);   exit; }

  // ตรวจไอเท็มปลายทาง
  $it = $pdo->prepare("SELECT id, user_id FROM items WHERE id=:id LIMIT 1");
  $it->execute([':id'=>$itemId]);
  $item = $it->fetch();
  if (!$item) { echo json_encode(['ok'=>false,'msg'=>'item_not_found']); exit; }

  $otherId = (int)$item['user_id'];
  if ($otherId === $uid) { echo json_encode(['ok'=>false,'msg'=>'self']); exit; }

  // จับคู่ user ให้เรียงแน่นอน
  $a = min($uid, $otherId);
  $b = max($uid, $otherId);
  $k = $itemId; // ใช้เป็น item_key เมื่อจำเป็นต้องสร้างใหม่

  // 1) พยายาม reuse ห้องเดิมของ "คู่ผู้ใช้นี้" (ไม่สน item_key)
  $try = $pdo->prepare("
    SELECT id
    FROM conversations
    WHERE pair_a=:a AND pair_b=:b
    ORDER BY last_msg_at DESC, id DESC
    LIMIT 1
  ");
  $try->execute([':a'=>$a, ':b'=>$b]);
  if ($row = $try->fetch()) {
    echo json_encode(['ok'=>true, 'conv_id'=>(int)$row['id']]); exit;
  }

  // 2) ถ้าไม่มีห้องเลย -> สร้างใหม่ อิง item ปัจจุบันเป็น item_key
  $ins = $pdo->prepare("INSERT INTO conversations
    (item_id, user_a, user_b, pair_a, pair_b, item_key, created_at, last_msg_at)
    VALUES (:item, :u1, :u2, :a, :b, :k, NOW(), NOW())");
  $ins->execute([
    ':item'=>$itemId, ':u1'=>$uid, ':u2'=>$otherId,
    ':a'=>$a, ':b'=>$b, ':k'=>$k
  ]);

  echo json_encode(['ok'=>true, 'conv_id'=>(int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>'error']); // ถ้าต้อง debug ค่อย log $e->getMessage()
}
