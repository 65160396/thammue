<?php
// /exchangepage/api/items/list.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 12)));
$offset = ($page - 1) * $limit;
$catId  = (int)($_GET['category_id'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));
$mine   = (int)($_GET['mine'] ?? 0);

$cuid = me_id();

$where  = [];
$params = [];

if ($mine === 1) {
  if (!$cuid) json_err('unauthorized', 401);
  $where[] = 'i.user_id = :uid';
  $params[':uid'] = $cuid;
} else {
  $where[] = "i.visibility='public'";
}
if ($catId > 0) { $where[] = "i.category_id = :cid"; $params[':cid'] = $catId; }
if ($q !== '')  { $where[] = "(i.title LIKE :q OR i.description LIKE :q)"; $params[':q'] = "%{$q}%"; }

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM items i {$whereSql}");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$sql = "
  SELECT
    i.id, i.title, i.category_id, i.province, i.user_id,
    (SELECT path FROM item_images im WHERE im.item_id=i.id ORDER BY sort_order,id LIMIT 1) AS cover,
    (CASE WHEN i.user_id = :cuid THEN 1 ELSE 0 END) AS is_owner
  FROM items i
  {$whereSql}
  ORDER BY i.id DESC
  LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);
$params[':cuid'] = (int)$cuid;
foreach ($params as $k=>$v) { if ($k!==':lim' && $k!==':off') $stmt->bindValue($k,$v); }
$stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$CATS = [1=>'แฮนเมด',2=>'ของประดิษฐ์',3=>'ของใช้ทั่วไป',4=>'เสื้อผ้า',5=>'หนังสือ',6=>'ของสะสม'];

$items = array_map(function($r) use($CATS){
  return [
    'id'            => (int)$r['id'],
    'title'         => (string)$r['title'],
    'category_id'   => (int)$r['category_id'],
    'category_name' => $CATS[(int)$r['category_id']] ?? '-',
    'province'      => $r['province'] ?: null,
    'cover'         => pub_url($r['cover'] ?? null),
    'user_id'       => isset($r['user_id']) ? (int)$r['user_id'] : null,
    'is_owner'      => ((int)($r['is_owner'] ?? 0) === 1),
  ];
}, $rows ?? []);

json_ok([
  'items' => $items,
  'total' => $total,
  'page'  => $page,
  'limit' => $limit,
]);
