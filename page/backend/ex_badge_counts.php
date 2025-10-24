<?php
// /page/backend/ex_badge_counts.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$st = $mysqli->prepare("SELECT COUNT(*) FROM ex_requests WHERE owner_user_id=? AND status='pending'");
$st->bind_param("i", $uid);
$st->execute();
$pending_incoming = (int)$st->get_result()->fetch_row()[0];

$st = $mysqli->prepare("SELECT COUNT(*) FROM ex_notifications WHERE user_id=? AND is_read=0");
$st->bind_param("i", $uid);
$st->execute();
$unread_notis = (int)$st->get_result()->fetch_row()[0];

jok([
  'incoming_requests' => $pending_incoming,
  'unread_notifications' => $unread_notis
]);
