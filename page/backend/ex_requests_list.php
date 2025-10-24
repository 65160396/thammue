<?php
// /page/backend/ex_requests_list.php
require_once __DIR__ . '/ex__common.php';

$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// helper เช็คว่าคอลัมน์มีจริงไหม (กัน error ถ้า schema ต่างกันเล็กน้อย)
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st = $db->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute();
  return (bool)($st->get_result()->fetch_row()[0] ?? 0);
}

$has_pref = col_exists($m, 'ex_requests', 'preferred_place');
$has_note = col_exists($m, 'ex_requests', 'note');

$extra = '';
if ($has_pref) $extra .= ', r.preferred_place';
if ($has_note)  $extra .= ', r.note';

$sql = "SELECT
          r.id, r.status, r.created_at,
          r.requested_item_id, r.offered_item_id
          $extra,
          i_req.title AS req_title,  i_req.thumbnail_url AS req_thumb,
          i_off.title AS off_title,  i_off.thumbnail_url AS off_thumb
        FROM ex_requests r
        LEFT JOIN ex_items i_req ON i_req.id = r.requested_item_id
        LEFT JOIN ex_items i_off ON i_off.id = r.offered_item_id
        WHERE r.owner_user_id = ?
        ORDER BY r.id DESC
        LIMIT 100";

$st = $m->prepare($sql);
$st->bind_param("i", $uid);
$st->execute();
$rs = $st->get_result();

$incoming = [];
while ($r = $rs->fetch_assoc()) {
  $incoming[] = [
    'id' => (int)$r['id'],
    'status' => $r['status'],
    'created_at' => $r['created_at'],
    'requested_item_id' => (int)$r['requested_item_id'],
    'offered_item_id'   => (int)$r['offered_item_id'],
    'req_title'  => $r['req_title']  ?? '',
    'req_thumb'  => $r['req_thumb']  ?? '',
    'off_title'  => $r['off_title']  ?? '',
    'off_thumb'  => $r['off_thumb']  ?? '',
    'preferred_place' => $r['preferred_place'] ?? null,
    'note'            => $r['note'] ?? null,
  ];
}

echo json_encode(['ok'=>true, 'incoming'=>$incoming], JSON_UNESCAPED_UNICODE);
