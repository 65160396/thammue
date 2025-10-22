<?php
// /exchangepage/api/requests/list_incoming.php
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo    = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) json_err('กรุณาเข้าสู่ระบบ', 401);

$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$status = trim((string)($_GET['status'] ?? ''));
$q      = trim((string)($_GET['q'] ?? ''));

function pub_path(?string $p): ?string {
  return $p ? THAMMUE_BASE . '/' . ltrim($p,'/') : null;
}

$where  = ["i.user_id = :uid"];
$params = [':uid'=>$userId];

if ($status !== '') {
  if (!in_array($status, ['pending','accepted','rejected'], true)) json_err('invalid status', 400);
  $where[] = "r.status = :st"; $params[':st'] = $status;
}
if ($q !== '') {
  $where[] = "(i.title LIKE :kw OR ri.title LIKE :kw OR u.email LIKE :kw)";
  $params[':kw'] = "%{$q}%";
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$countSql = "
  SELECT COUNT(*)
  FROM requests r
  JOIN items i ON i.id = r.item_id
  JOIN users u ON u.id = r.requester_user_id
  LEFT JOIN items ri ON ri.id = r.requester_item_id
  $whereSql
";
$st = $pdo->prepare($countSql);
$st->execute($params);
$total = (int)$st->fetchColumn();

$listSql = "
  SELECT
    r.id, r.item_id, r.requester_user_id, r.requester_item_id, r.message,
    r.status, r.created_at, r.decided_at,
    i.title AS item_title,
    (SELECT path FROM item_images im WHERE im.item_id = r.item_id ORDER BY sort_order,id LIMIT 1) AS cover,
    u.display_name AS requester_name, u.email AS requester_email,
    ri.title AS requester_item_title,
    (SELECT path FROM item_images rim WHERE rim.item_id = ri.id ORDER BY sort_order,id LIMIT 1) AS requester_item_cover
  FROM requests r
  JOIN items i ON i.id = r.item_id
  JOIN users u ON u.id = r.requester_user_id
  LEFT JOIN items ri ON ri.id = r.requester_item_id
  $whereSql
  ORDER BY r.id DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($listSql);
foreach ($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

$rows = $st->fetchAll() ?: [];
foreach ($rows as &$row) {
  $row['cover']               = pub_path($row['cover'] ?? null);
  $row['requester_item_cover']= pub_path($row['requester_item_cover'] ?? null);
  $row['requester_user_id']   = (int)$row['requester_user_id'];
  $row['item_id']             = (int)$row['item_id'];
  $row['requester_item_id']   = isset($row['requester_item_id']) ? (int)$row['requester_item_id'] : null;
  $row['id']                  = (int)$row['id'];
}

json_ok([
  'items'=>$rows,
  'page'=>$page,
  'limit'=>$limit,
  'total'=>$total,
  'pages'=>(int)ceil($total / $limit)
]);
