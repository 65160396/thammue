<?php
// /exchangepage/api/items/create.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';

if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_err('Method Not Allowed', 405);

/* ----- DB + session (เช็คว่าพร้อม) ----- */
$pdo = db();
assert_db_alive($pdo);
$userId = me_id();
if ($userId <= 0) json_err('กรุณาเข้าสู่ระบบ', 401);

/* ----- รับค่า ----- */
$title        = trim((string)($_POST['title'] ?? ''));
$categoryId   = (int)($_POST['category_id'] ?? 0);
$description  = trim((string)($_POST['description'] ?? ''));
$wantTitle    = trim((string)($_POST['want_title'] ?? ''));
$wantCatId    = ($_POST['want_category_id'] ?? '') !== '' ? (int)$_POST['want_category_id'] : null;
$wantNote     = trim((string)($_POST['want_note'] ?? ''));
$province     = trim((string)($_POST['province'] ?? ''));
$district     = trim((string)($_POST['district'] ?? ''));
$subdistrict  = trim((string)($_POST['subdistrict'] ?? ''));
$zipcode      = trim((string)($_POST['zipcode'] ?? ''));
$place_detail = trim((string)($_POST['place_detail'] ?? ''));

/* ----- validate ขั้นต่ำ ----- */
if ($title === '' || $categoryId <= 0) json_err('กรุณากรอกชื่อสินค้าและหมวดหมู่ให้ครบ', 422);

/* ----- รูปภาพ ----- */
$savedFiles = [];
if (!empty($_FILES['images']['name'][0])) $savedFiles = save_uploaded_images($_FILES['images'], 'items');
if (!$savedFiles) json_err('กรุณาอัปโหลดรูปสินค้าอย่างน้อย 1 รูป', 422);

/* ----- บันทึก DB ----- */
$pdo->beginTransaction();
try {
  $ins = $pdo->prepare("
    INSERT INTO items
      (user_id, title, category_id, description,
       want_title, want_category_id, want_note,
       province, district, subdistrict, zipcode, place_detail,
       visibility, created_at)
    VALUES
      (:u, :t, :c, :d, :wt, :wc, :wn, :pv, :dt, :sd, :zc, :pd, 'public', NOW())
  ");
  $ins->execute([
    ':u'=>$userId, ':t'=>$title, ':c'=>$categoryId,
    ':d'=>$description ?: null, ':wt'=>$wantTitle ?: null, ':wc'=>$wantCatId, ':wn'=>$wantNote ?: null,
    ':pv'=>$province ?: null, ':dt'=>$district ?: null, ':sd'=>$subdistrict ?: null, ':zc'=>$zipcode ?: null,
    ':pd'=>$place_detail ?: null,
  ]);

  $itemId = (int)$pdo->lastInsertId();

  // บันทึกรูป (เก็บ path แบบ relative ภายใต้ /exchangepage/public/)
  $ord = 0;
  $stmtImg = $pdo->prepare("INSERT INTO item_images (item_id, path, sort_order) VALUES (:id, :p, :s)");
  foreach ($savedFiles as $basename) {
    $publicPath = 'uploads/items/' . $basename;
    $stmtImg->execute([':id'=>$itemId, ':p'=>$publicPath, ':s'=>$ord++]);
  }

  $pdo->commit();
  json_ok([
    'id'          => $itemId,
    'item_id'     => $itemId,
    'detail_url'  => THAMMUE_BASE . "/public/detail.html?id={$itemId}&view=public",
    'success_url' => THAMMUE_BASE . "/public/success.html?id={$itemId}",
  ], 201);

} catch (Throwable $e) {
  $pdo->rollBack();
  // ส่งรายละเอียด SQL error ออกมาให้ debug
  json_err('บันทึกไม่สำเร็จ', 500, ['msg'=>$e->getMessage()]);
}
