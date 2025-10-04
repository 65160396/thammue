<?php
require __DIR__ . '/../../db.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;

// รับค่าจากฟอร์ม
$title       = trim($_POST['title'] ?? '');
$categoryId  = (int)($_POST['category_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

$wantTitle   = trim($_POST['want_title'] ?? '');
$wantCatId   = !empty($_POST['want_category_id']) ? (int)$_POST['want_category_id'] : null;
$wantNote    = trim($_POST['want_note'] ?? '');

$province    = trim($_POST['province'] ?? '');
$district    = trim($_POST['district'] ?? '');
$subdistrict = trim($_POST['subdistrict'] ?? '');
$zipcode     = trim($_POST['zipcode'] ?? '');
$placeDetail = trim($_POST['place_detail'] ?? '');

// ตรวจสอบข้อมูล
if ($title === '' || $categoryId <= 0 || empty($_FILES['images'])) {
    http_response_code(422);
    exit('กรุณากรอกข้อมูลให้ครบและอัปโหลดรูปอย่างน้อย 1 รูป');
}

$pdo->beginTransaction();
try {
    // 1) บันทึก exchange_items
    $stmt = $pdo->prepare("
    INSERT INTO exchange_items
      (user_id, category_id, title, description,
       want_title, want_category_id, want_note, status)
    VALUES (:uid,:cid,:title,:descr,:wtitle,:wcat,:wnote,'active')
  ");
    $stmt->execute([
        ':uid' => $userId,
        ':cid' => $categoryId,
        ':title' => $title,
        ':descr' => $description,
        ':wtitle' => $wantTitle ?: null,
        ':wcat' => $wantCatId,
        ':wnote' => $wantNote ?: null,
    ]);
    $itemId = (int)$pdo->lastInsertId();

    // 2) อัปโหลดรูป -> exchange_item_images
    $dir = __DIR__ . "/../../uploads/exchange/$itemId";
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $isCover = 1;
    foreach ($_FILES['images']['error'] as $i => $err) {
        if ($err !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES['images']['tmp_name'][$i];
        $name = $_FILES['images']['name'][$i];

        $mime = mime_content_type($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $new = uniqid('img_', true) . '.' . $ext;
        move_uploaded_file($tmp, "$dir/$new");
        $public = "/uploads/exchange/$itemId/$new";

        $pdo->prepare("INSERT INTO exchange_item_images (item_id,path,is_cover) VALUES (?,?,?)")
            ->execute([$itemId, $public, $isCover]);
        $isCover = 0; // รูปแรกเป็นปก
    }

    // 3) ที่อยู่ -> exchange_item_locations
    $pdo->prepare("
    INSERT INTO exchange_item_locations
      (item_id, province, district, subdistrict, zipcode, place_detail, is_primary)
    VALUES (?,?,?,?,?,?,1)
  ")->execute([$itemId, $province, $district, $subdistrict, $zipcode, $placeDetail]);

    $pdo->commit();

    // เสร็จแล้ว redirect ไปหน้า list
    header("Location: /backend/exchange/exchange_index.php?created=1");
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "บันทึกไม่สำเร็จ: " . htmlspecialchars($e->getMessage());
}
