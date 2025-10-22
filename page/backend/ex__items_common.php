<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

function item_columns(mysqli $m): array {
  $dbRes = $m->query("SELECT DATABASE()"); $db = $dbRes->fetch_row()[0];
  $cols = [];
  $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='items'";
  $st = $m->prepare($sql); $st->bind_param("s", $db); $st->execute();
  $rs = $st->get_result();
  while($r = $rs->fetch_assoc()){ $cols[] = $r['COLUMN_NAME']; }
  return $cols;
}
