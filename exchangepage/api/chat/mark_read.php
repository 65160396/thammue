<?php
// /exchangepage/api/chat/mark_read.php
require __DIR__ . '/../_config.php';

$pdo = db();
$uid = me_id();
$cid = (int)($_POST['conv_id'] ?? $_GET['conv_id'] ?? 0);
if(!$uid || !$cid) { echo json_encode(['ok'=>false,'msg'=>'BAD_REQ']); exit; }

// ตรวจสิทธิ์
$chk = $pdo->prepare("SELECT 1 FROM conversations WHERE id=:c AND (user_a=:u OR user_b=:u) LIMIT 1");
$chk->execute([':c'=>$cid, ':u'=>$uid]);
if(!$chk->fetch()){ echo json_encode(['ok'=>false,'msg'=>'FORBIDDEN']); exit; }

// หา id ล่าสุดของห้อง
$last = $pdo->prepare("SELECT id FROM messages WHERE conv_id=:c ORDER BY id DESC LIMIT 1");
$last->execute([':c'=>$cid]);
$lastId = (int)($last->fetch()['id'] ?? 0);

// upsert last_seen
$pdo->prepare("
  INSERT INTO conversation_reads (conv_id,user_id,last_seen_msg_id)
  VALUES (:c,:u,:m)
  ON DUPLICATE KEY UPDATE last_seen_msg_id = GREATEST(last_seen_msg_id, VALUES(last_seen_msg_id))
")->execute([':c'=>$cid, ':u'=>$uid, ':m'=>$lastId]);

// เหลือ unread ในห้องนี้
$cnt = $pdo->prepare("SELECT COUNT(*) AS n FROM messages WHERE conv_id=:c AND id > :m AND sender_id <> :u");
$cnt->execute([':c'=>$cid, ':m'=>$lastId, ':u'=>$uid]);
$left = (int)$cnt->fetch()['n'];

// รวม unread ทั้งหมด
$st = $pdo->prepare("SELECT id FROM conversations WHERE user_a=:u OR user_b=:u LIMIT 200");
$st->execute([':u'=>$uid]);
$convs = $st->fetchAll(); $sum=0;
if($convs){
  $getSeen = $pdo->prepare("SELECT last_seen_msg_id FROM conversation_reads WHERE conv_id=:c AND user_id=:u LIMIT 1");
  $cntNew  = $pdo->prepare("SELECT COUNT(*) AS n FROM messages WHERE conv_id=:c AND id > :s AND sender_id <> :u");
  foreach($convs as $cv){
    $cid2 = (int)$cv['id'];
    $getSeen->execute([':c'=>$cid2, ':u'=>$uid]);
    $seen = (int)($getSeen->fetch()['last_seen_msg_id'] ?? 0);
    $cntNew->execute([':c'=>$cid2, ':s'=>$seen, ':u'=>$uid]);
    $sum += (int)$cntNew->fetch()['n'];
  }
}
echo json_encode(['ok'=>true,'conv_unread'=>$left,'total_unread'=>$sum], JSON_UNESCAPED_UNICODE);
