<?php
// /page/backend/ex_list_my_items.php
$REQUIRE_LOGIN = true;                              // ← ต้องล็อกอิน
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// สามารถรองรับแบ่งหน้าได้ภายหลัง; ตอนนี้ดึงทั้งหมดของผู้ใช้
$table = EX_ITEMS_TABLE;

$st = $mysqli->prepare("
  SELECT id, title, description, thumbnail_url, created_at
  FROM `$table`
  WHERE user_id = ?
  ORDER BY COALESCE(updated_at, created_at) DESC
");
$st->bind_param('i', $uid);
$st->execute();
$rs = $st->get_result();

$items = [];
while ($r = $rs->fetch_assoc()) {
  $items[] = [
    'id'            => (int)$r['id'],
    'title'         => $r['title'] ?: '',
    'description'   => $r['description'] ?: '',
    'thumbnail_url' => $r['thumbnail_url'] ?: '',
    'created_at'    => $r['created_at'] ?: null,
  ];
}

echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE);
