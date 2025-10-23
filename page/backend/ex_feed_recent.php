<?php
// /page/backend/ex_feed_recent.php
require_once __DIR__ . '/ex__items_common.php'; // $mysqli, $uid, EX_ITEMS_TABLE

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$limit  = max(1, min( (int)($_GET['limit'] ?? 20), 48 ));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$table = EX_ITEMS_TABLE;

// เอาคอลัมน์จังหวัดให้แมพได้ทั้งชื่อใหม่/เก่า
$select = "
  id, user_id, title, thumbnail_url, category_id,
  COALESCE(province, addr_province) AS province,
  COALESCE(updated_at, created_at) AS ts
";

if ($uid) {
  $sql = "SELECT $select FROM `$table`
          WHERE user_id <> ?
          ORDER BY ts DESC
          LIMIT ? OFFSET ?";
  $st = $mysqli->prepare($sql);
  $st->bind_param('iii', $uid, $limit, $offset);
} else {
  $sql = "SELECT $select FROM `$table`
          ORDER BY ts DESC
          LIMIT ? OFFSET ?";
  $st = $mysqli->prepare($sql);
  $st->bind_param('ii', $limit, $offset);
}
$st->execute();
$rs = $st->get_result();

$items = [];
while ($r = $rs->fetch_assoc()) {
  $items[] = [
    'id'            => (int)$r['id'],
    'user_id'       => (int)$r['user_id'],
    'title'         => (string)($r['title'] ?? ''),
    'thumbnail_url' => (string)($r['thumbnail_url'] ?? ''),
    'category_id'   => isset($r['category_id']) ? (int)$r['category_id'] : null,
    'province'      => $r['province'] ?? null,
    'updated_at'    => $r['ts'] ?? null,
  ];
}

echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);
