<?php
// /thammue/api/items/show.php
require __DIR__ . '/../_config.php';

// ถ้ายังไม่ได้เปิด session ให้เปิด (บางโปรเจกต์ไปเปิดใน _config.php แล้ว)
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo    = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// === helper ===
function cat_name(int $cid): string {
  static $CATS = [
    1=>'แฮนเมด', 2=>'ของประดิษฐ์', 3=>'ของใช้ทั่วไป',
    4=>'เสื้อผ้า', 5=>'หนังสือ', 6=>'ของสะสม'
  ];
  return $CATS[$cid] ?? 'อื่น ๆ';
}

// ปรับให้เป็น path ที่เสิร์ฟได้จริง (แก้ BASE ให้ตรงกับโปรเจกต์คุณ)
function pub_path(?string $p): ?string {
  if (!$p) return null;
  // เก็บใน DB เป็น uploads/items/xxx.jpg => แปลงเป็น /thammue/uploads/items/xxx.jpg
  $p = ltrim($p, '/');
  return '/thammue/' . $p;
}

// === รับพารามิเตอร์ id หรือ ref ===
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ref = isset($_GET['ref']) ? strtoupper(trim((string)$_GET['ref'])) : '';

if ($id <= 0 && $ref !== '') {
  // รองรับรูปแบบ EX000123 -> id = 123 (ปัด 0 หน้าเลข)
  if (preg_match('/^EX0*([0-9]+)$/', $ref, $m)) {
    $id = (int)$m[1];
  }
}

if ($id <= 0) {
  json_err('missing id/ref', 400);
}

// === ดึงข้อมูลสินค้า + ตรวจสิทธิ์แสดงผล ===
// เงื่อนไข: ถ้าเป็นเจ้าของ -> เห็น public/pending, ถ้าไม่ใช่ -> เห็นแค่ public
$sql = "
  SELECT i.*
  FROM items i
  WHERE i.id = :id
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $id]);
$item = $st->fetch();

if (!$item) {
  json_err('ไม่พบสินค้า', 404);
}

$isOwner = ($userId > 0 && (int)$item['user_id'] === $userId);
if (!$isOwner && $item['visibility'] !== 'public') {
  json_err('สินค้านี้ยังไม่เปิดเผยสาธารณะ', 403);
}

// === โหลดรูปทั้งหมดของสินค้า ===
// [CHANGE] เดิมดึงเฉพาะ path; ตอนนี้ดึง id และ sort_order มาด้วย
$imgSt = $pdo->prepare("
  SELECT id, path, sort_order
  FROM item_images
  WHERE item_id = :id
  ORDER BY sort_order, id
");
$imgSt->execute([':id' => $id]);
$rows = $imgSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// สร้างทั้ง 1) ลิสต์ URL แบบเดิม (ไม่ทำให้ FE เก่าพัง) และ 2) ลิสต์แบบละเอียดสำหรับหน้าแก้ไข
$images        = [];      // แบบเดิม: array ของ URL
$images_detail = [];      // [ADD] แบบละเอียด: [{id,url,sort,is_cover}]
$minSort       = null;

foreach ($rows as $r) {
  $url = pub_path($r['path'] ?? null);
  if ($url) $images[] = $url;

  $sort = isset($r['sort_order']) ? (int)$r['sort_order'] : 0;
  $minSort = is_null($minSort) ? $sort : min($minSort, $sort);

  $images_detail[] = [
    'id'   => (int)$r['id'],
    'url'  => $url,
    'sort' => $sort,
    // is_cover จะเซ็ตด้านล่างหลังรู้ $minSort
  ];
}

// mark is_cover ใน images_detail
if (!empty($images_detail)) {
  foreach ($images_detail as &$im) {
    $im['is_cover'] = ($im['sort'] === $minSort); // [ADD]
  }
  unset($im);
}

$cover  = $images[0] ?? null; // รูปแรกตาม sort = หน้าปก

// === ข้อมูลเจ้าของ (ปกปิดข้อมูลอ่อนไหว) ===
$ownSt = $pdo->prepare("SELECT id, display_name AS name FROM users WHERE id = :u LIMIT 1");
$ownSt->execute([':u' => (int)$item['user_id']]);
$owner = $ownSt->fetch() ?: ['id' => (int)$item['user_id'], 'name' => null];

// === เช็ก favorite ของผู้ใช้ปัจจุบัน ===
$isFav = false;
if ($userId > 0) {
  $favSt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=:u AND item_id=:i LIMIT 1");
  $favSt->execute([':u'=>$userId, ':i'=>$id]);
  $isFav = (bool)$favSt->fetch();
}

// === สร้าง payload สำหรับ FE ===
$out = [
  'id'            => (int)$item['id'],
  'title'         => (string)$item['title'],
  'category_id'   => (int)$item['category_id'],
  'category_name' => cat_name((int)$item['category_id']),
  'description'   => $item['description'] ?? null,

  'want_title'       => $item['want_title'] ?? null,
  'want_category_id' => isset($item['want_category_id']) ? (int)$item['want_category_id'] : null,
  'want_note'        => $item['want_note'] ?? null,

  'province'     => $item['province'] ?? null,
  'district'     => $item['district'] ?? null,
  'subdistrict'  => $item['subdistrict'] ?? null,
  'zipcode'      => $item['zipcode'] ?? null,
  'place_detail' => $item['place_detail'] ?? null,

  'visibility'   => $item['visibility'],
  'created_at'   => $item['created_at'],

  'cover'        => $cover,
  'images'       => $images,         // แบบเดิม: ลิสต์ URL (คงไว้เพื่อไม่ให้หน้าเก่าพัง)
  'images_detail'=> $images_detail,  // [ADD] แบบละเอียดสำหรับหน้า edit/จัดการรูป

  'owner'        => ['id' => (int)$owner['id'], 'name' => $owner['name']],
  'is_owner'     => $isOwner,
  'is_favorite'  => $isFav,
];

// เคส: ถ้าอยากได้ ref กลับไปด้วย
$out['ref'] = 'EX' . str_pad((string)$out['id'], 6, '0', STR_PAD_LEFT);

$resp = $out;
$resp['item'] = $out;
json_ok($resp);
