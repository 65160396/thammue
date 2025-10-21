<?php
// /page/store/product_update.php
session_start();
require __DIR__ . '/../backend/config.php';

$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

function safe($s)
{
    return trim($s ?? '');
}
function asFloat($s)
{
    $s = str_replace(',', '', (string)$s);
    return $s === '' ? null : (float)$s;
}

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized', 401);
    $uid = (int)$_SESSION['user_id'];

    $id       = (int)($_POST['id'] ?? 0);
    $shopId   = (int)($_POST['shop_id'] ?? 0);
    $name     = safe($_POST['name'] ?? '');
    $category = (int)($_POST['category_id'] ?? 0);
    $desc     = safe($_POST['description'] ?? '');
    $price    = asFloat($_POST['price'] ?? '');
    $stock    = max(0, (int)($_POST['stock_qty'] ?? 0));

    if ($id <= 0 || $shopId <= 0 || $name === '' || $category <= 0 || $desc === '' || $price === null) {
        throw new Exception('กรอกข้อมูลให้ครบถ้วน', 422);
    }

    // สิทธิ์ร้าน
    $chk = $pdo->prepare("SELECT id FROM shops WHERE id=? AND user_id=?");
    $chk->execute([$shopId, $uid]);
    if (!$chk->fetch()) throw new Exception('forbidden', 403);

    // สินค้าอยู่ในร้านนี้จริงไหม
    $p = $pdo->prepare("SELECT id, main_image FROM products WHERE id=? AND shop_id=?");
    $p->execute([$id, $shopId]);
    if (!$p->fetch()) throw new Exception('not found', 404);

    $pdo->beginTransaction();

    // อัปเดตข้อมูลหลัก
    $u = $pdo->prepare("UPDATE products
  SET name=?, category_id=?, description=?, price=?, stock_qty=?
  WHERE id=? AND shop_id=?");
    $u->execute([$name, $category, $desc, number_format($price, 2, '.', ''), $stock, $id, $shopId]);


    // โฟลเดอร์รูป
    $base = realpath(__DIR__ . '/../uploads/products') ?: (__DIR__ . '/../uploads/products');
    if (!is_dir($base)) @mkdir($base, 0777, true);
    $dir = $base . "/{$id}";
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    // รูปหลักใหม่ (ถ้ามี)
    if (isset($_FILES['image_main']) && is_uploaded_file($_FILES['image_main']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image_main']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) $ext = 'jpg';
        $fname = 'main_' . time() . '.' . $ext;
        $abs   = $dir . '/' . $fname;
        if (!move_uploaded_file($_FILES['image_main']['tmp_name'], $abs)) {
            throw new Exception('อัปโหลดรูปหลักไม่สำเร็จ');
        }
        $rel = '/page/uploads/products/' . $id . '/' . $fname; // สำคัญ: เก็บเป็น /page/...
        $pdo->prepare("UPDATE products SET main_image=? WHERE id=?")->execute([$rel, $id]);
    }

    // รูปเพิ่มเติมใหม่ (ถ้ามี)
    if (!empty($_FILES['image_extra']['name']) && is_array($_FILES['image_extra']['name'])) {
        $n = count($_FILES['image_extra']['name']);
        for ($i = 0; $i < $n; $i++) {
            if (empty($_FILES['image_extra']['name'][$i]) || !is_uploaded_file($_FILES['image_extra']['tmp_name'][$i])) continue;
            $ext = strtolower(pathinfo($_FILES['image_extra']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) $ext = 'jpg';
            $fname = 'extra_' . ($i + 1) . '_' . time() . '.' . $ext;
            $abs = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['image_extra']['tmp_name'][$i], $abs)) {
                $rel = '/page/uploads/products/' . $id . '/' . $fname;
                $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)")->execute([$id, $rel]);
            }
        }
    }

    $pdo->commit();
    header('Location: /page/storepage/store-products.html?shop_id=' . $shopId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo "อัปเดตไม่สำเร็จ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
