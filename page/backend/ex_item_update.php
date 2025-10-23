<?php
// /page/backend/ex_item_update.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$table = EX_ITEMS_TABLE;

$item_id = (int)($_POST['item_id'] ?? ($_GET['item_id'] ?? 0));
if ($item_id <= 0) jerr('bad_id', 400);

// ตรวจว่าเป็นของตัวเอง
$st0 = $mysqli->prepare("SELECT user_id FROM `$table` WHERE id=?");
$st0->bind_param('i', $item_id);
$st0->execute();
$own = $st0->get_result()->fetch_row();
if (!$own) jerr('not_found', 404);
if ((int)$own[0] !== (int)$uid) jerr('forbidden', 403);

// รับข้อมูลได้ทั้ง JSON และ multipart/form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

if ($isMultipart) {
  $title        = trim((string)($_POST['title'] ?? ''));
  $description  = trim((string)($_POST['description'] ?? ''));
  $price        = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null;
  $category_id  = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $province     = trim((string)($_POST['province'] ?? ''));
  $district     = trim((string)($_POST['district'] ?? ''));
  $subdistrict  = trim((string)($_POST['subdistrict'] ?? ''));
  $zipcode      = trim((string)($_POST['zipcode'] ?? ''));
  $place_detail = trim((string)($_POST['place_detail'] ?? ''));
} else {
  $input        = json_decode(file_get_contents('php://input'), true) ?? [];
  $title        = trim((string)($input['title'] ?? ''));
  $description  = trim((string)($input['description'] ?? ''));
  $price        = array_key_exists('price',$input) ? (float)$input['price'] : null;
  $category_id  = array_key_exists('category_id',$input) ? (int)$input['category_id'] : null;
  $province     = trim((string)($input['province'] ?? ''));
  $district     = trim((string)($input['district'] ?? ''));
  $subdistrict  = trim((string)($input['subdistrict'] ?? ''));
  $zipcode      = trim((string)($input['zipcode'] ?? ''));
  $place_detail = trim((string)($input['place_detail'] ?? ''));
}

// อัปโหลด thumbnail (ถ้ามี)
$thumbUrl = null;
if ($isMultipart && !empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES['thumbnail']['tmp_name'];
  $f    = @getimagesize($tmp);
  if ($f !== false) {
    $ext = image_type_to_extension($f[2], true);
    if (in_array(strtolower($ext), ['.jpg','.jpeg','.png','.webp','.gif'])) {
      $baseDir = __DIR__ . '/../../uploads/items';
      if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
      $fname  = sprintf('%s_%d_upd_%s%s', date('YmdHis'), $uid, substr(md5($tmp.mt_rand()),0,6), $ext);
      $dest   = $baseDir . '/' . $fname;
      if (move_uploaded_file($tmp, $dest)) $thumbUrl = '/uploads/items/' . $fname;
    }
  }
}

// สร้าง SET ตามคอลัมน์ที่มีจริง
$cols = item_columns($mysqli);
$sets = []; $types=''; $vals=[];

$apply = function($col, $val, $type='s') use (&$cols,&$sets,&$types,&$vals){
  if (in_array($col, $cols)) { $sets[]="`$col`=?"; $types.=$type; $vals[]=$val; }
};

if ($title !== '' && in_array('title',$cols)) { $apply('title', $title, 's'); }
if (in_array('description',$cols))          { $apply('description', $description, 's'); }
if ($price !== null && in_array('price',$cols)) { $apply('price', $price, 'd'); }
if ($category_id !== null && in_array('category_id',$cols)) { $apply('category_id', $category_id, 'i'); }
if ($province !== '' && in_array('province',$cols)) { $apply('province', $province, 's'); }
if ($district !== '' && in_array('district',$cols)) { $apply('district', $district, 's'); }
if ($subdistrict !== '' && in_array('subdistrict',$cols)) { $apply('subdistrict', $subdistrict, 's'); }
if ($zipcode !== '' && in_array('zipcode',$cols)) { $apply('zipcode', $zipcode, 's'); }
if ($place_detail !== '' && in_array('place_detail',$cols)) { $apply('place_detail', $place_detail, 's'); }
if ($thumbUrl && in_array('thumbnail_url',$cols)) { $apply('thumbnail_url', $thumbUrl, 's'); }
if (in_array('updated_at',$cols)) { $sets[]='`updated_at`=NOW()'; }

if (!$sets) jerr('no_change', 400);

$sql = "UPDATE `$table` SET ".implode(',', $sets)." WHERE id=?";
$st = $mysqli->prepare($sql);
$types .= 'i'; $vals[] = $item_id;
$st->bind_param($types, ...$vals);
if (!$st->execute()) jerr('db_update_failed', 500);

echo json_encode(['ok'=>true, 'id'=>$item_id, 'thumbnail_url'=>$thumbUrl], JSON_UNESCAPED_UNICODE);
