<?php
// /page/backend/ex_feed_nearby.php
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$province = trim((string)($_GET['province'] ?? ''));
if ($province === '') {
  echo json_encode(['ok'=>true, 'items'=>[]], JSON_UNESCAPED_UNICODE);
  exit;
}

$limit  = max(1, min( (int)($_GET['limit'] ?? 20), 48 ));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$table = EX_ITEMS_TABLE;
$select = "
  id, user_id, title, thumbnail_url, category_id,
  COALESCE(province, addr_province) AS province,
  COALESCE(updated_at, created_at) AS ts
";

if ($uid) {
  $sql = "SELECT $select FROM `$table`
          WHERE COALESCE(province, addr_province) = ? AND user_id <> ?
          ORDER BY ts DESC
          LIMIT ? OFFSET ?";
  $st = $mysqli->prepare($sql);
  $st->bind_param('siii', $province, $uid, $limit, $offset);
} else {
  $sql = "SELECT $select FROM `$table`
          WHERE COALESCE(province, addr_province) = ?
          ORDER BY ts DESC
          LIMIT ? OFFSET ?";
  $st = $mysqli->prepare($sql);
  $st->bind_param('sii', $province, $limit, $offset);
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
