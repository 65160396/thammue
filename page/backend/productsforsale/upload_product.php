<?php
session_start();

/* === DB === */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

/* === รับค่า === */
$name        = trim($_POST['name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$shop_id     = isset($_POST['shop_id']) && $_POST['shop_id'] !== '' ? (int)$_POST['shop_id'] : null;

/* validate ขั้นต่ำ */
if ($name === '' || $category_id <= 0 || $description === '' || empty($_FILES['image_main']['name'])) {
    http_response_code(422);
    exit('กรอกข้อมูลและเลือกรูปหลักให้ครบ');
}

/* === เตรียมโฟลเดอร์อัปโหลด === */
$base = __DIR__ . '/../../uploads/products';
if (!is_dir($base)) @mkdir($base, 0777, true);

/* === บันทึก === */
$pdo->beginTransaction();
try {
    // 1) insert product (main_image ว่างไว้ก่อน)
    $sql = "INSERT INTO products (name, category_id, description, main_image, created_at"
        . ($shop_id ? ", shop_id" : "")
        . ") VALUES (:name,:cid,:desc,'',NOW()"
        . ($shop_id ? ", :sid" : "")
        . ")";
    $st = $pdo->prepare($sql);
    $st->bindValue(':name', $name);
    $st->bindValue(':cid', $category_id, PDO::PARAM_INT);
    $st->bindValue(':desc', $description);
    if ($shop_id) $st->bindValue(':sid', $shop_id, PDO::PARAM_INT);
    $st->execute();
    $productId = (int)$pdo->lastInsertId();

    // สร้างโฟลเดอร์ของสินค้า
    $dir = $base . '/' . $productId;
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    // 2) รูปหลัก
    $ext = strtolower(pathinfo($_FILES['image_main']['name'], PATHINFO_EXTENSION));
    $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $ext : 'jpg';
    $mainName = 'main_' . time() . '.' . $ext;
    $abs = $dir . '/' . $mainName;
    $rel = '/uploads/products/' . $productId . '/' . $mainName;
    if (!move_uploaded_file($_FILES['image_main']['tmp_name'], $abs)) {
        throw new Exception('อัปโหลดรูปหลักไม่สำเร็จ');
    }
    $pdo->prepare("UPDATE products SET main_image=:p WHERE id=:id")
        ->execute([':p' => $rel, ':id' => $productId]);

    // 3) รูปเพิ่มเติม (ถ้ามี)
    if (!empty($_FILES['image_extra']) && is_array($_FILES['image_extra']['name'])) {
        $n = count($_FILES['image_extra']['name']);
        for ($i = 0; $i < $n; $i++) {
            if ($_FILES['image_extra']['name'][$i] === '' || !is_uploaded_file($_FILES['image_extra']['tmp_name'][$i])) continue;
            $ext = strtolower(pathinfo($_FILES['image_extra']['name'][$i], PATHINFO_EXTENSION));
            $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $ext : 'jpg';
            $name = 'extra_' . ($i + 1) . '_' . time() . '.' . $ext;
            $abs2 = $dir . '/' . $name;
            $rel2 = '/uploads/products/' . $productId . '/' . $name;
            if (move_uploaded_file($_FILES['image_extra']['tmp_name'][$i], $abs2)) {
                $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (:pid,:path)")
                    ->execute([':pid' => $productId, ':path' => $rel2]);
            }
        }
    }

    $pdo->commit();
    header('Location: /page/storepage/upload_success.html?product_id=' . $productId);
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
