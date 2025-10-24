<?php
// /page/backend/ex_requests_list.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function list_incoming(mysqli $m, int $uid){
  $sql = "SELECT r.id, r.status, r.note, r.created_at, r.updated_at,
                 r.requester_user_id,
                 r.requested_item_id, r.offered_item_id,
                 i1.title AS requested_title, i1.thumbnail_url AS requested_thumb,
                 i2.title AS offered_title,   i2.thumbnail_url AS offered_thumb
          FROM ex_requests r
          JOIN ex_items i1 ON i1.id=r.requested_item_id
          JOIN ex_items i2 ON i2.id=r.offered_item_id
          WHERE r.owner_user_id=?
          ORDER BY r.id DESC LIMIT 200";
  $st = $m->prepare($sql);
  $st->bind_param("i", $uid);
  $st->execute();
  return stmt_all_assoc($st);
}
function list_outgoing(mysqli $m, int $uid){
  $sql = "SELECT r.id, r.status, r.note, r.created_at, r.updated_at,
                 r.owner_user_id,
                 r.requested_item_id, r.offered_item_id,
                 i1.title AS requested_title, i1.thumbnail_url AS requested_thumb,
                 i2.title AS offered_title,   i2.thumbnail_url AS offered_thumb
          FROM ex_requests r
          JOIN ex_items i1 ON i1.id=r.requested_item_id
          JOIN ex_items i2 ON i2.id=r.offered_item_id
          WHERE r.requester_user_id=?
          ORDER BY r.id DESC LIMIT 200";
  $st = $m->prepare($sql);
  $st->bind_param("i", $uid);
  $st->execute();
  return stmt_all_assoc($st);
}

jok([
  'incoming' => list_incoming($mysqli, $uid),
  'outgoing' => list_outgoing($mysqli, $uid),
]);
