<?php
// /exchangepage/api/chat/open.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../_config.php';

try {
  $pdo    = db();
  $uid    = (int) me_id();
  // รองรับสองรูปแบบ: ?with_owner_of=<item_id> (จากหน้า detail) และ ?user_id=<uid>
  $itemId = (int) ($_GET['with_owner_of'] ?? 0);
  $otherIdParam = (int) ($_GET['user_id'] ?? 0);

  if (!$uid) { echo json_encode(['ok'=>false,'msg'=>'not_login']); exit; }

  if ($itemId <= 0 && $otherIdParam <= 0) { echo json_encode(['ok'=>false,'msg'=>'bad_req']); exit; }

  if ($itemId > 0) {
    $it = $pdo->prepare("SELECT id, user_id FROM items WHERE id=:id LIMIT 1");
    $it->execute([':id'=>$itemId]);
    $item = $it->fetch();
    if (!$item) { echo json_encode(['ok'=>false,'msg'=>'item_not_found']); exit; }
    $otherId = (int)$item['user_id'];
  } else {
    $otherId = $otherIdParam;
  }

  if ($otherId === $uid) { echo json_encode(['ok'=>false,'msg'=>'self']); exit; }

  $a = min($uid, $otherId);
  $b = max($uid, $otherId);

  // ถ้ามีห้องของคู่ผู้ใช้นี้อยู่แล้ว reuse
  $try = $pdo->prepare("SELECT id FROM conversations WHERE pair_a=:a AND pair_b=:b ORDER BY last_msg_at DESC, id DESC LIMIT 1");
  $try->execute([':a'=>$a, ':b'=>$b]);
  if ($row = $try->fetch()) { echo json_encode(['ok'=>true, 'conv_id'=>(int)$row['id']]); exit; }

  // ไม่มี → สร้างใหม่ (ผูก item_id ถ้ามี)
  $ins = $pdo->prepare("INSERT INTO conversations
    (item_id, user_a, user_b, pair_a, pair_b, item_key, created_at, last_msg_at)
    VALUES (:item, :u1, :u2, :a, :b, :k, NOW(), NOW())");
  $ins->execute([
    ':item'=>$itemId ?: null,
    ':u1'=>$uid, ':u2'=>$otherId,
    ':a'=>$a, ':b'=>$b, ':k'=>$itemId ?: null
  ]);

  echo json_encode(['ok'=>true, 'conv_id'=>(int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>'error']);
}
