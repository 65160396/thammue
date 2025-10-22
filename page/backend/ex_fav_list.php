<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $mysqli->prepare("
  SELECT i.id, i.title, i.thumbnail_url AS thumb
  FROM ex_favorites f
  JOIN items i ON i.id=f.item_id
  WHERE f.user_id=?
  ORDER BY f.created_at DESC
  LIMIT 500
");
$st->bind_param("i", $uid);
$st->execute();
$items = stmt_all_assoc($st);
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
