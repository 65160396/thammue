<?php
// /page/backend/ex_item_get.php
require_once __DIR__ . '/ex__items_common.php';  // $uid พร้อมใช้งานที่นี่ (อาจเป็น null)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jerr('bad_id', 400);

$table = EX_ITEMS_TABLE;

// อ่านทั้งแถว เพื่อรองรับคอลัมน์ที่มี/ไม่มี
$st = $mysqli->prepare("SELECT * FROM `$table` WHERE id=?");
$st->bind_param('i', $id);
$st->execute();
$it = $st->get_result()->fetch_assoc();
if (!$it) jerr('not_found', 404);

// รวมรูป (ใช้ thumbnail_url เป็นรูปแรก)
$images = [];
if (!empty($it['thumbnail_url'])) $images[] = $it['thumbnail_url'];

// ตั้งชื่อเจ้าของ (ถ้าต้องการ JOIN users ภายหลังค่อยเสริม)
$owner_name = 'ผู้ใช้';

// แผนที่หมวดหมู่ → ชื่อ
$CATEGORY_MAP = [
  1 => 'แฮนเมด',
  2 => 'ของประดิษฐ์',
  3 => 'ของใช้ทั่วไป',
  4 => 'เสื้อผ้า',
  5 => 'หนังสือ',
  6 => 'ของสะสม',
];
$category_id   = isset($it['category_id']) ? (int)$it['category_id'] : null;
$category_name = $category_id ? ($CATEGORY_MAP[$category_id] ?? null) : null;

// รองรับชื่อคอลัมน์จากฟอร์ม
$addr_province    = $it['province']    ?? $it['addr_province']    ?? null;
$addr_district    = $it['district']    ?? $it['addr_district']    ?? null;
$addr_subdistrict = $it['subdistrict'] ?? $it['addr_subdistrict'] ?? null;
$addr_zipcode     = $it['zipcode']     ?? $it['addr_zipcode']     ?? null;
$place_detail     = $it['place_detail'] ?? null;

$out = [
  'id'            => (int)$it['id'],
  'title'         => (string)($it['title'] ?? ''),
  'description'   => (string)($it['description'] ?? ''),
  'thumbnail_url' => (string)($it['thumbnail_url'] ?? ''),
  'images'        => $images,

  // หมวดหมู่
  'category_id'   => $category_id,
  'category_name' => $category_name,

  // ที่อยู่
  'addr_province'    => $addr_province,
  'addr_district'    => $addr_district,
  'addr_subdistrict' => $addr_subdistrict,


  // สถานที่นัดรับ (ผู้ใช้กรอกเอง)
  'place_detail'     => $place_detail,

  'owner_id'   => (int)$it['user_id'],
  'owner_name' => $owner_name,
  'is_owner'   => ($uid && (int)$uid === (int)$it['user_id']),   // << ส่งให้หน้า detail เช็กได้ง่าย ๆ

  'ref_code'   => 'EX' . str_pad($it['id'], 6, '0', STR_PAD_LEFT),
  'created_at' => $it['created_at'] ?? null,
];

echo json_encode(['ok'=>true, 'item'=>$out], JSON_UNESCAPED_UNICODE);
