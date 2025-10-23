<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$item_id = (int)($input['item_id'] ?? 0);
$action  = trim((string)($input['action'] ?? 'toggle'));
if ($item_id<=0) jerr('bad_request');

if ($action==='remove'){
  $st = $mysqli->prepare("DELETE FROM ex_favorites WHERE user_id=? AND item_id=?");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  echo json_encode(['ok'=>true,'removed'=>true], JSON_UNESCAPED_UNICODE); exit;
}
// toggle default
$st = $mysqli->prepare("SELECT 1 FROM ex_favorites WHERE user_id=? AND item_id=?");
$st->bind_param("ii", $uid, $item_id);
$st->execute();
$has = $st->get_result()->fetch_row();

if ($has){
  $st = $mysqli->prepare("DELETE FROM ex_favorites WHERE user_id=? AND item_id=?");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  echo json_encode(['ok'=>true,'favorited'=>false], JSON_UNESCAPED_UNICODE); exit;
} else {
  $st = $mysqli->prepare("INSERT INTO ex_favorites (user_id, item_id, created_at) VALUES (?,?,NOW())");
  $st->bind_param("ii", $uid, $item_id);
  $st->execute();
  echo json_encode(['ok'=>true,'favorited'=>true], JSON_UNESCAPED_UNICODE); exit;
}
// ... ดึง $itemOwnerId จากตาราง ex_items ให้ได้ก่อน (เช่น SELECT user_id FROM ex_items WHERE id=?)
if ((int)$itemOwnerId === (int)$uid) {
  jerr('cannot_fav_own_item', 400);
}

