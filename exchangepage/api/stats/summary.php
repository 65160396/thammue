<?php
// /exchangepage/api/stats/summary.php
require_once __DIR__ . '/../_config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = db();
$userId = me_id();
if (!$userId) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }

/* pending requests ที่เข้ามาหา “สินค้าของฉัน” */
$st = $pdo->prepare("
  SELECT COUNT(*)
  FROM requests r
  JOIN items i ON r.item_id = i.id
  WHERE i.user_id = :u AND r.status = 'pending'
");
$st->execute([':u'=>$userId]);
$total_pending = (int)$st->fetchColumn();

/* unread messages ทั้งหมดในห้องที่ผู้ใช้เป็นสมาชิก (ใช้ conversation_reads) */
$st = $pdo->prepare("
  SELECT c.id AS conv_id
  FROM conversations c
  WHERE c.user_a=:u OR c.user_b=:u
  LIMIT 500
");
$st->execute([':u'=>$userId]);
$convs = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

$total_unread = 0;
if ($convs) {
  $getSeen = $pdo->prepare("SELECT last_seen_msg_id FROM conversation_reads WHERE conv_id=:c AND user_id=:u LIMIT 1");
  $cntNew  = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE conv_id=:c AND id > :seen AND sender_id <> :u");
  foreach ($convs as $cid) {
    $getSeen->execute([':c'=>$cid, ':u'=>$userId]);
    $seen = (int)($getSeen->fetchColumn() ?: 0);
    $cntNew->execute([':c'=>$cid, ':seen'=>$seen, ':u'=>$userId]);
    $total_unread += (int)$cntNew->fetchColumn();
  }
}

/* favorites count (optional) */
$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :u");
$st->execute([':u'=>$userId]);
$total_favorites = (int)$st->fetchColumn();

echo json_encode([
  'ok'              => true,
  'total_favorites' => $total_favorites,
  'total_pending'   => $total_pending,
  'total_unread'    => $total_unread,
], JSON_UNESCAPED_UNICODE);
