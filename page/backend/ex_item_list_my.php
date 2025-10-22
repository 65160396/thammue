<?php
require_once __DIR__ . '/ex__items_common.php';
$st = $mysqli->prepare("SELECT id, title, IFNULL(price,NULL) AS price, IFNULL(thumbnail_url,'') AS thumbnail_url, IFNULL(updated_at,'') AS updated_at FROM items WHERE user_id=? ORDER BY id DESC LIMIT 500");
$st->bind_param("i", $uid);
$st->execute();
$items = stmt_all_assoc($st);
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
