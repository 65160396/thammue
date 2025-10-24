<?php
// /page/backend/ex_meetings_list.php
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* -------- helpers: column discovery -------- */
function col_exists(mysqli $db, string $table, string $col): bool
{
  $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st = $db->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  return (bool)($st->get_result()->fetch_row()[0] ?? 0);
}
/* เลือกจากรายชื่อคงที่ */
function pick(mysqli $db, string $table, array $cands): ?string
{
  foreach ($cands as $c) if (col_exists($db, $table, $c)) return $c;
  return null;
}
/* เลือกจาก pattern (LIKE) เมื่อชื่อคอลัมน์แปลก */
function pick_like(mysqli $db, string $table, array $patterns): ?string
{
  $where = [];
  foreach ($patterns as $p) $where[] = "LOWER(COLUMN_NAME) LIKE ?";
  $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND (" . implode(' OR ', $where) . ")
          ORDER BY ORDINAL_POSITION LIMIT 1";
  $st = $db->prepare($sql);
  $bind = [$table];
  $types = 's';
  foreach ($patterns as $p) {
    $bind[] = '%' . strtolower($p) . '%';
    $types .= 's';
  }
  $st->bind_param($types, ...$bind);
  $st->execute();
  $row = $st->get_result()->fetch_row();
  return $row[0] ?? null;
}

try {
  $m = dbx();
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $uid = me();
  if (!$uid) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
  }

  /* ---------- image column on ex_items ---------- */
  $itemImg = pick($m, 'ex_items', ['thumbnail_url', 'image_url', 'image', 'img', 'photo_url']);
  if (!$itemImg) {
    // ลองเดาแบบ pattern
    $itemImg = pick_like($m, 'ex_items', ['thumb', 'image', 'img', 'photo', 'pic']);
  }

  /* ---------- address columns on ex_user_kyc (requester) ---------- */
  $kycProv = pick($m, 'ex_user_kyc', ['province', 'province_name', 'prov_name', 'prov', 'province_th']);
  if (!$kycProv) $kycProv = pick_like($m, 'ex_user_kyc', ['province', 'prov', 'changwat', 'จังหวัด']);

  $kycDist = pick($m, 'ex_user_kyc', ['district', 'district_name', 'amphoe', 'amphur', 'amphure_name', 'district_th']);
  if (!$kycDist) $kycDist = pick_like($m, 'ex_user_kyc', ['district', 'amphur', 'amphoe', 'อำเภ', 'อำเภอ']);

  $kycSubd = pick($m, 'ex_user_kyc', ['subdistrict', 'subdistrict_name', 'tambon', 'tambon_name', 'subdistrict_th']);
  if (!$kycSubd) $kycSubd = pick_like($m, 'ex_user_kyc', ['subdistrict', 'tambon', 'ตำบล']);

  $reqThumbSel = $itemImg ? "i_req.`$itemImg` AS req_thumb," : "NULL AS req_thumb,";
  $offThumbSel = $itemImg ? "i_off.`$itemImg` AS off_thumb," : "NULL AS off_thumb,";

  $provSel = $kycProv ? "uk.`$kycProv` AS from_province," : "NULL AS from_province,";
  $distSel = $kycDist ? "uk.`$kycDist` AS from_district," : "NULL AS from_district,";
  $subdSel = $kycSubd ? "uk.`$kycSubd` AS from_subdistrict," : "NULL AS from_subdistrict,";

  $sql = "
    SELECT
      r.id AS request_id,
      r.status,
      r.meet_date  AS r_meet_date,
      r.meet_time  AS r_meet_time,
      r.meet_place AS r_meet_place,
      r.meet_note  AS r_meet_note,
      r.meet_at    AS r_meet_at,
      r.meeting_at AS r_meeting_at,
      r.meeting_note AS r_meeting_note,
      r.updated_at,

      i_req.title AS req_title,
      i_off.title AS off_title,
      $reqThumbSel
      $offThumbSel
      $provSel
      $distSel
      $subdSel

      m.meet_date  AS m_meet_date,
      m.meet_time  AS m_meet_time,
      m.meet_place AS m_meet_place,
      m.meet_note  AS m_meet_note,
      m.scheduled_at AS m_scheduled_at,
      m.place      AS m_place,
      m.note       AS m_note
    FROM ex_requests r
      LEFT JOIN ex_items i_req ON i_req.id = r.requested_item_id
      LEFT JOIN ex_items i_off ON i_off.id = r.offered_item_id
      LEFT JOIN ex_user_kyc uk ON uk.user_id = r.requester_user_id
      LEFT JOIN ex_meetings m ON m.request_id = r.id
    WHERE (r.owner_user_id=? OR r.requester_user_id=?)
      AND r.status IN ('accepted','pending')
    ORDER BY COALESCE(r.updated_at, r.created_at) DESC
    LIMIT 200
  ";
  $st = $m->prepare($sql);
  $st->bind_param('ii', $uid, $uid);
  $st->execute();
  $rs = $st->get_result();

  $out = [];
  while ($r = $rs->fetch_assoc()) {
    // combine datetime
    $scheduled_at = null;
    if (!empty($r['r_meet_date']) && !empty($r['r_meet_time'])) {
      $scheduled_at = $r['r_meet_date'] . ' ' . $r['r_meet_time'];
    } elseif (!empty($r['r_meet_at'])) {
      $scheduled_at = $r['r_meet_at'];
    } elseif (!empty($r['r_meeting_at'])) {
      $scheduled_at = $r['r_meeting_at'];
    } elseif (!empty($r['m_scheduled_at'])) {
      $scheduled_at = $r['m_scheduled_at'];
    }

    $meet_when = null;
    if ($scheduled_at) {
      $ts = strtotime($scheduled_at);
      if ($ts) $meet_when = date('d/m/Y H:i', $ts) . ' น.';
    }

    $place = $r['r_meet_place'] ?? null;
    if (!$place) $place = $r['m_meet_place'] ?: ($r['m_place'] ?? null);

    $note = $r['r_meet_note'] ?? null;
    if (!$note) $note = $r['r_meeting_note'] ?: ($r['m_meet_note'] ?? ($r['m_note'] ?? null));

    $out[] = [
      'request_id'       => (int)$r['request_id'],
      'status'           => $r['status'],
      'scheduled_at'     => $scheduled_at,
      'meet_when'        => $meet_when,
      'meet_place'       => $place,
      'meet_note'        => $note,
      'updated_at'       => $r['updated_at'],

      'req_title'        => $r['req_title'] ?: '',
      'off_title'        => $r['off_title'] ?: '',
      'req_thumb'        => $r['req_thumb'] ?: null,
      'off_thumb'        => $r['off_thumb'] ?: null,

      'from_province'    => $r['from_province'] ?: null,
      'from_district'    => $r['from_district'] ?: null,
      'from_subdistrict' => $r['from_subdistrict'] ?: null,
    ];
  }

  echo json_encode(['ok' => true, 'meetings' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => 'sql_fail: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
