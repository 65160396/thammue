<?php
// /page/backend/ex_fav_list.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

/* ensure table exists */
$m->query("CREATE TABLE IF NOT EXISTS ".T_FAVORITES." (
  user_id INT NOT NULL, item_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, item_id), KEY(item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* list favorites for user (join to ex_items) */
$st = $m->prepare("
  SELECT f.item_id, i.title, i.thumbnail_url
  FROM ".T_FAVORITES." f
  JOIN ".T_ITEMS." i ON i.id = f.item_id
  WHERE f.user_id=?
  ORDER BY f.created_at DESC
");
$st->bind_param("i", $uid);
$st->execute();
$rows = stmt_all_assoc($st);
echo json_encode(['ok'=>true,'favorites'=>$rows], JSON_UNESCAPED_UNICODE);
