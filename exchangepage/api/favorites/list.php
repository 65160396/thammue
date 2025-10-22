<?php
// /exchangepage/api/favorites/list.php
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();
$uid = me_id();
if (!$uid) json_err('AUTH', 401);

// หมวดหมู่ (ใช้ชื่อเดียวกับฝั่งหน้าเว็บ)
$CATS = [1=>'แฮนเมด',2=>'ของประดิษฐ์',3=>'ของใช้ทั่วไป',4=>'เสื้อผ้า',5=>'หนังสือ',6=>'ของสะสม'];

$sql = "
  SELECT i.id, i.title, i.category_id, i.province,
         (SELECT path FROM item_images im WHERE im.item_id=i.id ORDER BY sort_order,id LIMIT 1) AS cover
  FROM favorites f
  JOIN items i ON i.id = f.item_id
  WHERE f.user_id = :u
  ORDER BY f.created_at DESC, i.id DESC
";
$st = $pdo->prepare($sql);
$st->execute([':u'=>$uid]);
$rows = $st->fetchAll() ?: [];

$items = array_map(function($r) use($CATS){
  return [
    'id'            => (int)$r['id'],
    'title'         => (string)$r['title'],
    'category_id'   => (int)$r['category_id'],
    'category_name' => $CATS[(int)$r['category_id']] ?? '-',
    'province'      => $r['province'] ?: null,
    'cover'         => pub_url($r['cover'] ?? null), // แปลงเป็น URL ใช้งานได้จริง
  ];
}, $rows);

json_ok(['items'=>$items]);
