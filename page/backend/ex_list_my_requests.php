<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();

$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $mysqli->prepare("SELECT id,requested_item_id,offered_item_id,status,created_at FROM ex_requests WHERE owner_user_id=? ORDER BY id DESC");
$st->bind_param("i", $uid);
$st->execute();
$incoming = stmt_all_assoc($st);

$st = $mysqli->prepare("SELECT id,requested_item_id,offered_item_id,status,created_at FROM ex_requests WHERE requester_user_id=? ORDER BY id DESC");
$st->bind_param("i", $uid);
$st->execute();
$outgoing = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'incoming'=>$incoming,'outgoing'=>$outgoing], JSON_UNESCAPED_UNICODE);
