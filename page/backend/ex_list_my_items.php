<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $mysqli->prepare("SELECT id, title, thumbnail_url AS thumb FROM items WHERE user_id=? ORDER BY id DESC LIMIT 200");
$st->bind_param("i", $uid);
$st->execute();
$items = stmt_all_assoc($st);
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
