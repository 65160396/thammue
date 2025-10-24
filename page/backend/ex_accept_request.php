<?php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$rid = (int)($in['request_id'] ?? 0);
if ($rid<=0) jerr('bad_request', 400);

$m->begin_transaction();
try {
  $st = $m->prepare("
    SELECT r.id, r.status, r.requested_item_id, r.offered_item_id,
           it_req.user_id AS owner_user_id, it_off.user_id AS requester_user_id
    FROM ".T_REQUESTS." r
    JOIN ".T_ITEMS." it_req ON it_req.id = r.requested_item_id
    JOIN ".T_ITEMS." it_off ON it_off.id = r.offered_item_id
    WHERE r.id=? FOR UPDATE
  ");
  $st->bind_param("i", $rid);
  $st->execute();
  $req = stmt_one_assoc($st);
  if (!$req) jerr('not_found', 404);

  $owner = (int)$req['owner_user_id'];    // เจ้าของของที่ถูกขอ
  $requester = (int)$req['requester_user_id'];

  if ($uid !== $owner) jerr('forbidden', 403);

  $st2 = $m->prepare("UPDATE ".T_REQUESTS." SET status='accepted', updated_at=NOW() WHERE id=?");
  $st2->bind_param("i", $rid);
  $st2->execute();

  // แจ้งผู้ขอ
  $typ='request_accepted'; $title='คำขอแลกได้รับการยอมรับ'; $body='ผู้ขายยอมรับคำขอแลกของคุณ';
  $ins = $m->prepare("INSERT INTO ".T_NOTIFICATIONS." (user_id,type,ref_id,title,body,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
  $ins->bind_param("isiss", $requester, $typ, $rid, $title, $body);
  $ins->execute();

  $m->commit();
  jok();
} catch (Throwable $e) {
  $m->rollback();
  jerr('sql_fail: '.$e->getMessage(), 500);
}
