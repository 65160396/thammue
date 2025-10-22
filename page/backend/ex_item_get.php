<?php
require_once __DIR__ . '/ex__items_common.php';
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) jerr('bad_request');
$st = $mysqli->prepare("SELECT id, user_id, title, IFNULL(description,'') AS description, IFNULL(price,NULL) AS price, IFNULL(thumbnail_url,'') AS thumbnail_url, IFNULL(updated_at,'') AS updated_at FROM items WHERE id=?");
$st->bind_param("i", $id);
$st->execute();
$item = $st->get_result()->fetch_assoc();
if (!$item) jerr('not_found', 404);
if ((int)$item['user_id'] !== $uid) jerr('forbidden', 403); // owner only
echo json_encode(['ok'=>true,'item'=>$item], JSON_UNESCAPED_UNICODE);
