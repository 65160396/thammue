<?php
require_once __DIR__ . '/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx(); $uid = me();
if (!$uid) jerr('not_logged_in', 401);

/* ===== helpers ===== */
function table_cols(mysqli $m, string $table): array {
  $st = $m->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $st->bind_param("s", $table); $st->execute();
  $out = []; $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) $out[$r['COLUMN_NAME']] = true;
  return $out;
}
function first_col(array $cols, array $cands): ?string {
  foreach ($cands as $c) if (isset($cols[$c])) return $c;
  return null;
}

$counts = ['incoming_requests'=>0, 'favorites'=>0, 'unread_messages'=>0];

/* ===== requests (incoming) ===== */
$reqCols  = table_cols($m, T_REQUESTS);
$itemCols = table_cols($m, T_ITEMS);

$col_to_user = first_col($reqCols, ['to_user_id','recipient_id','target_user_id']);
$col_owner   = first_col($reqCols, ['owner_id','seller_id']);
$col_itemref = first_col($reqCols, ['target_item_id','item_id','product_id']);
$col_status  = first_col($reqCols, ['status','state','req_status']);

// เงื่อนไขสถานะที่ถือว่า "ยังค้าง"
$pendingCond = $col_status
  ? " AND r.`$col_status` IN ('pending','new','await','requested','open','0',0)"
  : "";

if ($col_to_user) {
  $sql = "SELECT COUNT(*) c FROM ".T_REQUESTS." r WHERE r.`$col_to_user`=?".$pendingCond;
  $st = $m->prepare($sql); $st->bind_param("i", $uid);
  $st->execute(); $counts['incoming_requests'] = (int)$st->get_result()->fetch_assoc()['c'];
} elseif ($col_owner) {
  $sql = "SELECT COUNT(*) c FROM ".T_REQUESTS." r WHERE r.`$col_owner`=?".$pendingCond;
  $st = $m->prepare($sql); $st->bind_param("i", $uid);
  $st->execute(); $counts['incoming_requests'] = (int)$st->get_result()->fetch_assoc()['c'];
} elseif ($col_itemref && isset($itemCols['user_id'])) {
  $sql = "SELECT COUNT(*) c FROM ".T_REQUESTS." r
          JOIN ".T_ITEMS." i ON i.id = r.`$col_itemref`
          WHERE i.user_id=?".$pendingCond;
  $st = $m->prepare($sql); $st->bind_param("i", $uid);
  $st->execute(); $counts['incoming_requests'] = (int)$st->get_result()->fetch_assoc()['c'];
}

/* ===== favorites ของฉัน ===== */
$col_fav_user = first_col(table_cols($m, T_FAVORITES), ['user_id','created_by']);
if ($col_fav_user) {
  $sql = "SELECT COUNT(*) c FROM ".T_FAVORITES." WHERE `$col_fav_user`=?";
  $st = $m->prepare($sql); $st->bind_param("i", $uid);
  $st->execute(); $counts['favorites'] = (int)$st->get_result()->fetch_assoc()['c'];
}

/* ===== chat unread ===== */
$chatCols = table_cols($m, T_CHAT_MESSAGES);
if (isset($chatCols['recipient_id']) && isset($chatCols['is_read'])) {
  $st = $m->prepare("SELECT COUNT(*) c FROM ".T_CHAT_MESSAGES." WHERE recipient_id=? AND is_read=0");
  $st->bind_param("i", $uid); $st->execute();
  $counts['unread_messages'] = (int)$st->get_result()->fetch_assoc()['c'];
}

/* ===== done ===== */
if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
jok($counts);
