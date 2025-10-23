<?php
$REQUIRE_LOGIN = false;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$limit  = max(1, (int)($_GET['limit']  ?? 20));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$table  = EX_ITEMS_TABLE;

if ($uid) {
  $sql = "
    SELECT id, user_id, title, description, thumbnail_url, category_id, province, created_at, updated_at
    FROM `$table`
    WHERE user_id <> ?
    ORDER BY COALESCE(updated_at, created_at) DESC
    LIMIT ?, ?
  ";
  $st = $mysqli->prepare($sql);
  $st->bind_param('iii', $uid, $offset, $limit);
} else {
  $sql = "
    SELECT id, user_id, title, description, thumbnail_url, category_id, province, created_at, updated_at
    FROM `$table`
    ORDER BY COALESCE(updated_at, created_at) DESC
    LIMIT ?, ?
  ";
  $st = $mysqli->prepare($sql);
  $st->bind_param('ii', $offset, $limit);
}
$st->execute();
$rs = $st->get_result();

$items = [];
while ($r = $rs->fetch_assoc()) {
  $items[] = [
    'id'=>(int)$r['id'],'user_id'=>(int)$r['user_id'],
    'title'=>$r['title']??'','description'=>$r['description']??'',
    'thumbnail_url'=>$r['thumbnail_url']??'',
    'category_id'=>isset($r['category_id'])?(int)$r['category_id']:null,
    'province'=>$r['province']??null,'created_at'=>$r['created_at']??null,
  ];
}
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
