<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();

$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $mysqli->prepare("SELECT id,type,ref_id,title,body,is_read,created_at FROM ex_notifications WHERE user_id=? ORDER BY id DESC LIMIT 100");
$st->bind_param("i", $uid);
$st->execute();
$items = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
