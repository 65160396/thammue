<?php
// /thammue/api/chat/unread_count.php
require __DIR__ . '/../_config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$uid = me_id();
if(!$uid) { echo json_encode(['ok'=>false,'msg'=>'AUTH']); exit; }

// ดึงห้องที่เราเป็นสมาชิก
$st = $pdo->prepare("
  SELECT c.id AS conv_id
  FROM conversations c
  WHERE c.user_a=:u OR c.user_b=:u
  ORDER BY c.last_msg_at DESC
  LIMIT 200
");
$st->execute([':u'=>$uid]);
$convs = $st->fetchAll();

$sum = 0;
if ($convs) {
  // เตรียม stmt ล่วงหน้า
  $getSeen = $pdo->prepare("SELECT last_seen_msg_id FROM conversation_reads WHERE conv_id=:c AND user_id=:u LIMIT 1");
  $cntNew  = $pdo->prepare("
    SELECT COUNT(*) AS n
    FROM messages
    WHERE conv_id=:c AND id > :seen AND sender_id <> :u
  ");

  foreach ($convs as $cv) {
    $cid = (int)$cv['conv_id'];

    $getSeen->execute([':c'=>$cid, ':u'=>$uid]);
    $seen = (int)($getSeen->fetch()['last_seen_msg_id'] ?? 0);

    $cntNew->execute([':c'=>$cid, ':seen'=>$seen, ':u'=>$uid]);
    $sum += (int)$cntNew->fetch()['n'];
  }
}

echo json_encode(['ok'=>true, 'unread'=>$sum], JSON_UNESCAPED_UNICODE);
