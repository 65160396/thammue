<?php
// /exchangepage/api/requests/update.php
require __DIR__ . '/../_config.php';
require __DIR__ . '/../notifications/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if(!$uid) json_err('AUTH', 401);

$id  = (int)($_POST['id'] ?? 0);
$act = (string)($_POST['action'] ?? '');
if ($id<=0 || !in_array($act, ['accept','reject'], true)) json_err('BAD_REQ', 400);

$st = $pdo->prepare("
  SELECT r.id, r.item_id, r.status,
         r.requester_user_id AS requester_id,
         i.user_id AS owner_id,
         i.title  AS item_title
  FROM requests r
  JOIN items i ON i.id = r.item_id
  WHERE r.id = :id
  LIMIT 1
");
$st->execute([':id'=>$id]);
$row = $st->fetch();
if (!$row) json_err('NOT_FOUND', 404);
if ((int)$row['owner_id'] !== $uid) json_err('FORBIDDEN', 403);
if ($row['status'] !== 'pending') json_ok(['status'=>$row['status']]);

$new = ($act==='accept') ? 'accepted' : 'rejected';

$pdo->beginTransaction();
try {
  $pdo->prepare("UPDATE requests SET status=:s, decided_at=NOW() WHERE id=:id")
      ->execute([':s'=>$new, ':id'=>$id]);

  $requesterId = (int)$row['requester_id'];
  if ($requesterId <= 0) throw new RuntimeException('REQUESTER_ID_MISSING');

  $itemTitle = (string)($row['item_title'] ?? '');
  $statusTxt = ($new==='accepted') ? 'ถูกตอบรับ' : 'ถูกปฏิเสธ';
  $title     = "คำขอแลกของคุณ{$statusTxt}";
  $body      = $itemTitle ? "รายการ: \"{$itemTitle}\" (คำขอ #{$id})" : "คำขอ #{$id}";
  $link      = THAMMUE_BASE . "/public/request-detail.html?id={$id}";

  notify($pdo, $requesterId, 'exchange', $title, $body, $link);

  $pdo->commit();
  json_ok(['status'=>$new]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_err('UPDATE_FAIL', 500, ['err'=>$e->getMessage()]);
}
