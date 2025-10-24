<?php
// /page/backend/ex_requests_list.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

try {
  // incoming = คำขอที่ "เขา" ขอของเรา (เราเป็น owner ของ requested_item)
  $sql_in = "
    SELECT r.*, it_req.title AS req_title, it_req.thumbnail_url AS req_thumb,
           it_off.title AS off_title, it_off.thumbnail_url AS off_thumb
    FROM ".T_REQUESTS." r
    JOIN ".T_ITEMS." it_req ON it_req.id = r.requested_item_id
    JOIN ".T_ITEMS." it_off ON it_off.id = r.offered_item_id
    WHERE it_req.user_id = ?
    ORDER BY r.id DESC
  ";
  $st = $m->prepare($sql_in);
  if (!$st) throw new Exception('prepare_failed_incoming: '.$m->error);
  $st->bind_param("i", $uid);
  $st->execute();
  $incoming = stmt_all_assoc($st);

  // outgoing = คำขอที่เราไปขอของเขา (เราเป็น owner ของ offered_item)
  $sql_out = "
    SELECT r.*, it_req.title AS req_title, it_req.thumbnail_url AS req_thumb,
           it_off.title AS off_title, it_off.thumbnail_url AS off_thumb
    FROM ".T_REQUESTS." r
    JOIN ".T_ITEMS." it_req ON it_req.id = r.requested_item_id
    JOIN ".T_ITEMS." it_off ON it_off.id = r.offered_item_id
    WHERE it_off.user_id = ?
    ORDER BY r.id DESC
  ";
  $st2 = $m->prepare($sql_out);
  if (!$st2) throw new Exception('prepare_failed_outgoing: '.$m->error);
  $st2->bind_param("i", $uid);
  $st2->execute();
  $outgoing = stmt_all_assoc($st2);

  echo json_encode(['ok'=>true, 'incoming'=>$incoming, 'outgoing'=>$outgoing], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>'exception', 'detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
