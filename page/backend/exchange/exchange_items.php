<?php
// /page/backend/exchange/exchange_items.php
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$q   = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$page= max(1, (int)($_GET['page'] ?? 1));
$per = max(1, min(30, (int)($_GET['per'] ?? 12)));
$off = ($page-1)*$per;

$where = "WHERE status='active'";
$params = [];
$types  = '';

if ($q !== '') {
  $where .= " AND (title LIKE CONCAT('%',?,'%') OR description LIKE CONCAT('%',?,'%') OR wanted LIKE CONCAT('%',?,'%'))";
  $types .= 'sss'; $params[]=$q; $params[]=$q; $params[]=$q;
}
if ($cat > 0) {
  $where .= " AND category_id=?";
  $types .= 'i'; $params[]=$cat;
}

$total = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM exchange_items $where");
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute(); $stmt->bind_result($total); $stmt->fetch(); $stmt->close();

$sql = "
SELECT i.id,i.title,i.category_id,i.wanted,i.created_at,
       (SELECT file_path FROM exchange_item_images WHERE item_id=i.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
FROM exchange_items i
$where
ORDER BY i.id DESC
LIMIT ? OFFSET ?
";
$params2 = $params;
$types2  = $types . 'ii';
$params2[]= $per; $params2[]=$off;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'total' => (int)$total,
  'page'  => $page,
  'per'   => $per,
  'items' => $res,
], JSON_UNESCAPED_UNICODE);
