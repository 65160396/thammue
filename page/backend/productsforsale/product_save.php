<?php
require __DIR__ . '/../config.php';

/* ฟังก์ชันช่วยสร้างชื่อไฟล์ใหม่ให้ปลอดภัยและไม่ซ้ำกัน*/
function safe_filename($name)
{
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9-_]/', '_', $base);
    return $base . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . strtolower($ext) : '');
}

/* อ่านค่าข้อมูลสินค้าจากฟอร์มที่ส่งมาทาง POST*/
$name        = trim($_POST['name'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

/*  แปลงราคาที่มีเครื่องหมาย , ให้เป็นตัวเลขทศนิยม เช่น 1,250.50 → 1250.50*/
$priceStr = trim($_POST['price'] ?? '');
$priceStr = str_replace(',', '', $priceStr);
$price     = ($priceStr === '' ? null : (float)$priceStr);

/* <<< ใหม่: จำนวนคงเหลือ >>> */
$stock_qty = max(0, (int)($_POST['stock_qty'] ?? 0));

$province    = trim($_POST['province'] ?? '');
//ตรวจสอบว่ากรอกข้อมูลบังคับครบหรือยัง
if ($name === '' || !$category_id || $description === '') {
    http_response_code(422);
    exit('กรอกข้อมูลให้ครบถ้วน');
}

/* ===== ตรวจสอบและเตรียมโฟลเดอร์เก็บรูปภาพ ===== */
$uploadDir = __DIR__ . '/../uploads/products/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

/* ===== ตรวจสอบและอัปโหลด "รูปปกหลัก" main image ===== */
if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('อัปโหลดรูปปกไม่สำเร็จ');
}

$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    http_response_code(400);
    exit('ไฟล์รูปไม่รองรับ (อนุญาต: jpg, jpeg, png, webp, gif)');
}
//
$mainFileName = safe_filename($_FILES['main_image']['name']);
$mainTarget   = $uploadDir . $mainFileName;
if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $mainTarget)) {
    http_response_code(500);
    exit('ย้ายไฟล์รูปปกไม่สำเร็จ');
}
$mainPublicPath = '/uploads/products/' . $mainFileName;

/* เพิ่มข้อมูลสินค้าใหม่ลงฐานข้อมูล (ตาราง products) */
$stmt = $mysqli->prepare("
    INSERT INTO products (name, category_id, description, price, province, main_image, stock_qty)
    VALUES (?,?,?,?,?,?,?)
");
$stmt->bind_param(
    "sisdssi",            // s name, i category, s desc, d price, s province, s main_image, i stock_qty
    $name,
    $category_id,
    $description,
    $price,               // ถ้าไม่มีราคา $price จะเป็น NULL ได้
    $province,
    $mainPublicPath,
    $stock_qty
);
if (!$stmt->execute()) {
    @unlink($mainTarget);
    http_response_code(500);
    exit('บันทึกสินค้าไม่สำเร็จ: ' . $stmt->error);
}
$productId = $stmt->insert_id;
$stmt->close();

/* ===== optional: รูปเพิ่มเติม (multiple) ===== */
if (!empty($_FILES['images']['name'][0])) {
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext2 = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext2, $allowed)) continue;

        $fileName2 = safe_filename($_FILES['images']['name'][$i]);
        $target2   = $uploadDir . $fileName2;
        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target2)) {
            $public2 = '/uploads/products/' . $fileName2;
            $stmt2 = $mysqli->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)");
            $stmt2->bind_param("is", $productId, $public2);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

/* ===== กลับหน้าแรกพร้อมข้อความสำเร็จ ===== */
header("Location: /index.php?uploaded=1");
exit;
