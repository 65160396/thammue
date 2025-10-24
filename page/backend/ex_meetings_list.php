<?php
// /page/backend/ex_meetings_list.php
require_once __DIR__ . '/ex__common.php';

header('Content-Type: application/json; charset=utf-8');

$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

/** helper: check column exists (กัน schema แตกต่าง) */
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) 
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st = $db->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute();
  return (bool)($st->get_result()->fetch_row()[0] ?? 0);
}

$has_meet_date = col_exists($m,'ex_requests','meet_date');
$has_meet_time = col_exists($m,'ex_requests','meet_time');
$has_meet_place= col_exists($m,'ex_requests','meet_place');
$has_meet_note = col_exists($m,'ex_requests','meet_note');

$has_pref_place= col_exists($m,'ex_requests','preferred_place');
$has_req_note  = col_exists($m,'ex_requests','note');

/** address/place on items */
$addr_cols = [
  'addr_province','addr_district','addr_subdistrict','addr_zipcode','place_detail',
  // บาง schema ใช้ชื่อ province/district/subdistrict/zipcode
  'province','district','subdistrict','zipcode'
];
$select_req_addr = [];
$select_off_addr = [];
foreach ($addr_cols as $c) {
  if (col_exists($m,'ex_items',$c)) {
    $select_req_addr[] = "i_req.`$c` AS req_$c";
    $select_off_addr[] = "i_off.`$c` AS off_$c";
  }
}

$extra_req = '';
if ($has_meet_date) $extra_req .= ', r.meet_date';
if ($has_meet_time) $extra_req .= ', r.meet_time';
if ($has_meet_place)$extra_req .= ', r.meet_place';
if ($has_meet_note) $extra_req .= ', r.meet_note';
if ($has_pref_place)$extra_req .= ', r.preferred_place';
if ($has_req_note)  $extra_req .= ', r.note';

$extra_req .= $select_req_addr ? ', '.implode(',', $select_req_addr) : '';
$extra_req .= $select_off_addr ? ', '.implode(',', $select_off_addr) : '';

/*
  เอารายการที่เราเกี่ยวข้องทั้งสองบทบาท
  (จะเห็นทั้งที่เราเป็นเจ้าของ และที่เราเป็นผู้ขอ)
*/
$sql = "SELECT
          r.id, r.status, r.created_at, r.updated_at,
          r.requester_user_id, r.owner_user_id,
          r.requested_item_id, r.offered_item_id
          $extra_req,
          i_req.title  AS req_title,  i_req.thumbnail_url AS req_thumb,
          i_off.title  AS off_title,  i_off.thumbnail_url AS off_thumb
        FROM ex_requests r
        LEFT JOIN ex_items i_req ON i_req.id = r.requested_item_id
        LEFT JOIN ex_items i_off ON i_off.id = r.offered_item_id
        WHERE (r.owner_user_id = ? OR r.requester_user_id = ?)
          AND r.status IN ('accepted','pending')
        ORDER BY COALESCE(r.updated_at, r.created_at) DESC
        LIMIT 200";

$st = $m->prepare($sql);
$st->bind_param("ii", $uid, $uid);
$st->execute();
$rs = $st->get_result();

$out = [];
while ($r = $rs->fetch_assoc()) {
  $side = ($uid == (int)$r['owner_user_id']) ? 'owner' : 'requester';

  // address summary (รองรับทั้ง addr_* และชื่อสั้น)
  $req_sub   = $r['req_addr_subdistrict'] ?? ($r['req_subdistrict'] ?? null);
  $req_prov  = $r['req_addr_province']    ?? ($r['req_province'] ?? null);
  $off_sub   = $r['off_addr_subdistrict'] ?? ($r['off_subdistrict'] ?? null);
  $off_prov  = $r['off_addr_province']    ?? ($r['off_province'] ?? null);

  $req_area = implode(' · ', array_filter([$req_sub, $req_prov]));
  $off_area = implode(' · ', array_filter([$off_sub, $off_prov]));

  // ดึงข้อมูลนัดหมาย (fallback จาก preferred_place/note ถ้าไม่มี meet_*)
  $meet_place = $r['meet_place'] ?? null;
  $meet_note  = $r['meet_note']  ?? null;
  if (!$meet_place && !empty($r['preferred_place'])) $meet_place = $r['preferred_place'];
  if (!$meet_note  && !empty($r['note']))            $meet_note  = $r['note'];

  $meet_date = $r['meet_date'] ?? null;
  $meet_time = $r['meet_time'] ?? null;

  $dt_text = '-';
  if ($meet_date || $meet_time) {
    $dt_text = trim(($meet_date ?: '') . ' ' . ($meet_time ?: ''));
  }

  $row = [
    'id'         => (int)$r['id'],
    'status'     => $r['status'],
    'side'       => $side, // owner | requester
    'created_at' => $r['created_at'],
    'updated_at' => $r['updated_at'] ?? $r['created_at'],

    // --- (1) คงคีย์เดิมแบบแบนไว้ (เผื่อหน้าอื่นใช้) ---
    'meet_date'  => $meet_date,
    'meet_time'  => $meet_time,
    'meet_when'  => $dt_text,
    'meet_place' => $meet_place ?: null,
    'meet_note'  => $meet_note  ?: null,

    // รายการสินค้าที่เกี่ยวข้อง
    'requested' => [
      'id'    => (int)$r['requested_item_id'],
      'title' => $r['req_title'] ?: '',
      'thumb' => $r['req_thumb'] ?: '',
      'area'  => $req_area ?: null,
      'place_detail' => $r['req_place_detail'] ?? null,
    ],
    'offered' => [
      'id'    => (int)$r['offered_item_id'],
      'title' => $r['off_title'] ?: '',
      'thumb' => $r['off_thumb'] ?: '',
      'area'  => $off_area ?: null,
      'place_detail' => $r['off_place_detail'] ?? null,
    ],
  ];

  // --- (2) เพิ่มคีย์แบบ nested ให้หน้า ex_meetings.html อ่านได้ทันที ---
  $row['meet'] = [
    'date'  => $meet_date ?: '',
    'time'  => $meet_time ?: '',
    'place' => $meet_place ?: '',
    'note'  => $meet_note ?: '',
  ];

  // สำหรับ UI ฝั่ง “ของฉัน” / “ของอีกฝ่าย”
  if ($side === 'owner') {
    $row['mine']   = $row['requested'];
    $row['theirs'] = $row['offered'];
  } else {
    $row['mine']   = $row['offered'];
    $row['theirs'] = $row['requested'];
  }

  $out[] = $row;
}

echo json_encode(['ok'=>true, 'meetings'=>$out], JSON_UNESCAPED_UNICODE);
