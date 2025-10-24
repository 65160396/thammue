<?php
// /page/backend/ex_item_list_my.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $m->prepare("
  SELECT id, title, COALESCE(thumbnail_url,'') AS thumb,
         COALESCE(price, NULL) AS price,
         COALESCE(updated_at, created_at) AS updated_at
  FROM ".T_ITEMS."
  WHERE user_id=?
  ORDER BY id DESC
  LIMIT 500
");
$st->bind_param("i", $uid);
$st->execute();
$items = stmt_all_assoc($st);

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
