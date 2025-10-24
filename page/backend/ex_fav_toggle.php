<?php
// /page/backend/ex_fav_toggle.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$item_id = (int)($in['item_id'] ?? 0);
$action  = strtolower(trim((string)($in['action'] ?? 'toggle')));
if ($item_id<=0) jerr('bad_request', 400);

/* create favorites table if not exists */
$m->query("CREATE TABLE IF NOT EXISTS ".T_FAVORITES." (
  user_id INT NOT NULL, item_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, item_id), KEY(item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($action === 'add') {
  $st = $m->prepare("INSERT IGNORE INTO ".T_FAVORITES." (user_id,item_id,created_at) VALUES (?,?,NOW())");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  jok(['favorited'=>true]);
} elseif ($action === 'remove') {
  $st = $m->prepare("DELETE FROM ".T_FAVORITES." WHERE user_id=? AND item_id=?");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  jok(['favorited'=>false]);
} else {
  $st = $m->prepare("SELECT 1 FROM ".T_FAVORITES." WHERE user_id=? AND item_id=? LIMIT 1");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  $has = $st->get_result()->fetch_row();
  if ($has) {
    $st = $m->prepare("DELETE FROM ".T_FAVORITES." WHERE user_id=? AND item_id=?");
    $st->bind_param("ii", $uid, $item_id);
    $st->execute();
    jok(['favorited'=>false]);
  } else {
    $st = $m->prepare("INSERT INTO ".T_FAVORITES." (user_id,item_id,created_at) VALUES (?,?,NOW())");
    $st->bind_param("ii", $uid, $item_id);
    $st->execute();
    jok(['favorited'=>true]);
  }
}
