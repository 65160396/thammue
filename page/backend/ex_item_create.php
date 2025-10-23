<?php
require_once __DIR__ . '/ex__items_common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  if (!$uid) jerr('NOT_LOGIN', 401);

  // ----- อ่าน input: รองรับทั้ง JSON และ multipart -----
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

  if ($isMultipart) {
    // จาก FormData
    $title         = trim((string)($_POST['title'] ?? ''));
    $price         = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
    $description   = trim((string)($_POST['description'] ?? ''));
    $thumbUrl      = trim((string)($_POST['thumbnail_url'] ?? ''));

    // >>> ฟิลด์ใหม่จากฟอร์ม (ชื่อให้ตรงกับ <input name="..."> ในหน้าอัปโหลด)
    $category_id   = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $province      = trim((string)($_POST['province'] ?? ''));
    $district      = trim((string)($_POST['district'] ?? ''));
    $subdistrict   = trim((string)($_POST['subdistrict'] ?? ''));
    $zipcode       = trim((string)($_POST['zipcode'] ?? ''));
    $place_detail  = trim((string)($_POST['place_detail'] ?? ''));
  } else {
    // จาก JSON
    $input         = json_decode(file_get_contents('php://input'), true) ?? [];
    $title         = trim((string)($input['title'] ?? ''));
    $price         = array_key_exists('price', $input) ? (float)$input['price'] : null;
    $description   = trim((string)($input['description'] ?? ''));
    $thumbUrl      = trim((string)($input['thumbnail_url'] ?? ''));

    $category_id   = isset($input['category_id']) ? (int)$input['category_id'] : null;
    $province      = trim((string)($input['province'] ?? ''));
    $district      = trim((string)($input['district'] ?? ''));
    $subdistrict   = trim((string)($input['subdistrict'] ?? ''));
    $zipcode       = trim((string)($input['zipcode'] ?? ''));
    $place_detail  = trim((string)($input['place_detail'] ?? ''));
  }

  if ($title === '') jerr('title_required');

  // ----- อัปโหลดไฟล์ (ถ้ามี) -----
  $uploadedUrls = [];
  if ($isMultipart && !empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $baseDir = __DIR__ . '/../../uploads/items';
    if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);

    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count; $i++) {
      if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
      $tmp  = $_FILES['images']['tmp_name'][$i];
      $name = basename($_FILES['images']['name'][$i]);

      $finfo = @getimagesize($tmp);
      if ($finfo === false) continue;

      $ext = image_type_to_extension($finfo[2], true);
      if (!in_array(strtolower($ext), ['.jpg','.jpeg','.png','.webp','.gif'])) continue;

      $fname  = sprintf('%s_%d_%s%s', date('YmdHis'), $uid, substr(md5($name.mt_rand()),0,6), $ext);
      $dest   = $baseDir . '/' . $fname;

      if (move_uploaded_file($tmp, $dest)) {
        $publicUrl = '/uploads/items/' . $fname;
        $uploadedUrls[] = $publicUrl;
      }
    }
  }

  // ตั้ง thumbnail จากรูปแรก ถ้าไม่ได้ส่งมา
  if ($thumbUrl === '' && !empty($uploadedUrls)) $thumbUrl = $uploadedUrls[0];

  // ----- เตรียม insert ตามคอลัมน์ที่ตารางมีจริง -----
  $table  = EX_ITEMS_TABLE;
  $cols   = item_columns($mysqli);

  $fields   = ['user_id','title'];
  $placeH   = ['?','?'];
  $types    = 'is';
  $bindVals = [$uid, $title];

  if (in_array('description', $cols))   { $fields[]='description';   $placeH[]='?'; $types.='s'; $bindVals[]=$description; }
  if (in_array('price', $cols) && $price !== null) { $fields[]='price'; $placeH[]='?'; $types.='d'; $bindVals[]=$price; }
  if (in_array('thumbnail_url', $cols) && $thumbUrl !== '') { $fields[]='thumbnail_url'; $placeH[]='?'; $types.='s'; $bindVals[]=$thumbUrl; }

  // >>> ใส่ฟิลด์ที่อยู่/หมวด ถ้าตารางมีคอลัมน์นั้น ๆ (กันสคีมาไม่ตรง)
  if (in_array('category_id', $cols) && $category_id !== null) { $fields[]='category_id'; $placeH[]='?'; $types.='i'; $bindVals[]=$category_id; }
  if (in_array('province', $cols)     && $province !== '')     { $fields[]='province';     $placeH[]='?'; $types.='s'; $bindVals[]=$province; }
  if (in_array('district', $cols)     && $district !== '')     { $fields[]='district';     $placeH[]='?'; $types.='s'; $bindVals[]=$district; }
  if (in_array('subdistrict', $cols)  && $subdistrict !== '')  { $fields[]='subdistrict';  $placeH[]='?'; $types.='s'; $bindVals[]=$subdistrict; }
  if (in_array('zipcode', $cols)      && $zipcode !== '')      { $fields[]='zipcode';      $placeH[]='?'; $types.='s'; $bindVals[]=$zipcode; }
  if (in_array('place_detail', $cols) && $place_detail !== '') { $fields[]='place_detail'; $placeH[]='?'; $types.='s'; $bindVals[]=$place_detail; }

  if (in_array('created_at', $cols))  { $fields[]='created_at'; $placeH[]='NOW()'; }
  if (in_array('updated_at', $cols))  { $fields[]='updated_at'; $placeH[]='NOW()'; }

  $sql = "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeH) . ")";
  $st  = $mysqli->prepare($sql);
  if (!$st) jerr('db_prepare_failed');

  if (strpos(implode('', $placeH), '?') !== false) {
    $st->bind_param($types, ...$bindVals);
  }

  if (!$st->execute()) jerr('db_execute_failed');

  $itemId = $mysqli->insert_id;

  echo json_encode([
    'ok' => true,
    'id' => (int)$itemId,
    'thumbnail_url' => $thumbUrl,
    'images' => $uploadedUrls,
    'success_url' => "/page/ex_item_success.html?id={$itemId}"
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
