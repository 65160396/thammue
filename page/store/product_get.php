<?php
// /page/store/product_get.php
session_start();
require __DIR__ . '/../backend/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized', 401);
    $uid = (int)$_SESSION['user_id'];

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $shopId = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
    if ($id <= 0 || $shopId <= 0) throw new Exception('bad request', 422);

    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ยืนยันสิทธิ์ร้าน
    $chk = $pdo->prepare("SELECT id FROM shops WHERE id=? AND user_id=?");
    $chk->execute([$shopId, $uid]);
    if (!$chk->fetch()) throw new Exception('forbidden', 403);

    $st = $pdo->prepare("SELECT id, shop_id, name, category_id, description, price, stock_qty, main_image
                       FROM products WHERE id=? AND shop_id=? LIMIT 1");
    $st->execute([$id, $shopId]);
    $product = $st->fetch();
    if (!$product) throw new Exception('not found', 404);

    $imgs = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id=? ORDER BY id ASC");
    $imgs->execute([$id]);
    $images = $imgs->fetchAll();

    // ปรับ path
    $fix = function ($p) {
        if (!$p) return $p;
        $p = str_replace('\\', '/', $p);
        if (strpos($p, '/uploads/') === 0) $p = '/page' . $p;
        return $p;
    };
    $product['main_image'] = $fix($product['main_image']);
    foreach ($images as &$im) {
        $im['image_path'] = $fix($im['image_path']);
    }
    unset($im);

    echo json_encode(['ok' => true, 'product' => $product, 'images' => $images]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
