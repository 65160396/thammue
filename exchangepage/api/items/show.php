<?php
// /exchangepage/api/items/show.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();
assert_db_alive($pdo);
$userId = me_id();

function cat_name(int $cid): string {
  static $CATS = [1=>'แฮนเมด',2=>'ของประดิษฐ์',3=>'ของใช้ทั่วไป',4=>'เสื้อผ้า',5=>'หนังสือ',6=>'ของสะสม'];
  return $CATS[$cid] ?? 'อื่น ๆ';
}
function pub_path(?string $p): ?string { return $p ? THAMMUE_BASE . '/' . ltrim($p,'/') : null; }

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ref = isset($_GET['ref']) ? strtoupper(trim((string)$_GET['ref'])) : '';
if ($id <= 0 && $ref !== '' && preg_match('/^EX0*([0-9]+)$/', $ref, $m)) $id = (int)$m[1];
if ($id <= 0) json_err('missing id/ref', 400);

$st = $pdo->prepare("SELECT * FROM items WHERE id = :id LIMIT 1");
$st->execute([':id'=>$id]);
$item = $st->fetch();
if (!$item) json_err('ไม่พบสินค้า', 404);

$isOwner = ($userId > 0 && (int)$item['user_id'] === $userId);
if (!$isOwner && ($item['visibility'] ?? 'public') !== 'public') json_err('สินค้านี้ยังไม่เปิดเผยสาธารณะ', 403);

$imgSt = $pdo->prepare("SELECT id, path, sort_order FROM item_images WHERE item_id = :id ORDER BY sort_order, id");
$imgSt->execute([':id'=>$id]);
$rows = $imgSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$images=[]; $images_detail=[]; $minSort=null;
foreach ($rows as $r) {
  $url = pub_path($r['path'] ?? null);
  if ($url) $images[] = $url;
  $sort = isset($r['sort_order']) ? (int)$r['sort_order'] : 0;
  $minSort = is_null($minSort) ? $sort : min($minSort, $sort);
  $images_detail[] = ['id'=>(int)$r['id'], 'url'=>$url, 'sort'=>$sort];
}
if (!empty($images_detail)) {
  foreach ($images_detail as &$im) $im['is_cover'] = ($im['sort'] === $minSort);
  unset($im);
}
$cover = $images[0] ?? null;

// ปรับให้เข้ากับ users ของระบบหลัก
$ownSt = $pdo->prepare("SELECT id, display_name AS name FROM users WHERE id = :u LIMIT 1");
$ownSt->execute([':u'=>(int)$item['user_id']]);
$owner = $ownSt->fetch() ?: ['id'=>(int)$item['user_id'], 'name'=>null];

$isFav = false;
if ($userId > 0) {
  $favSt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=:u AND item_id=:i LIMIT 1");
  $favSt->execute([':u'=>$userId, ':i'=>$id]);
  $isFav = (bool)$favSt->fetch();
}
$usersTable = defined('USERS_DB') ? USERS_DB . '.users' : 'users';

$ownSt = $pdo->prepare("
  SELECT id, COALESCE(display_name, name) AS name
  FROM {$usersTable}
  WHERE id = :u
  LIMIT 1
");
$ownSt->execute([':u' => (int)$item['user_id']]);


$out = [
  'id'=>(int)$item['id'],
  'title'=>(string)$item['title'],
  'category_id'=>(int)$item['category_id'],
  'category_name'=>cat_name((int)$item['category_id']),
  'description'=>$item['description'] ?? null,
  'want_title'=>$item['want_title'] ?? null,
  'want_category_id'=>isset($item['want_category_id']) ? (int)$item['want_category_id'] : null,
  'want_note'=>$item['want_note'] ?? null,
  'province'=>$item['province'] ?? null,
  'district'=>$item['district'] ?? null,
  'subdistrict'=>$item['subdistrict'] ?? null,
  'zipcode'=>$item['zipcode'] ?? null,
  'place_detail'=>$item['place_detail'] ?? null,
  'visibility'=>$item['visibility'] ?? 'public',
  'created_at'=>$item['created_at'] ?? null,
  'cover'=>$cover,
  'images'=>$images,
  'images_detail'=>$images_detail,
  'owner'=>['id'=>(int)$owner['id'], 'name'=>$owner['name']],
  'is_owner'=>$isOwner,
  'is_favorite'=>$isFav,
  'ref'=>'EX'.str_pad((string)$item['id'],6,'0',STR_PAD_LEFT),
];
json_ok(['item'=>$out] + $out);
