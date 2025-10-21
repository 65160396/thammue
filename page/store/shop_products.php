<?php
session_start();
require __DIR__ . '/../backend/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['shop_id'])) throw new Exception('missing shop_id');
    $shopId = (int)$_GET['shop_id'];
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized');

    $userId = (int)$_SESSION['user_id'];
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $chk = $pdo->prepare("SELECT id FROM shops WHERE id=? AND user_id=?");
    $chk->execute([$shopId, $userId]);
    if (!$chk->fetch()) throw new Exception('forbidden');

    $q = $pdo->prepare("SELECT id,name,price,stock_qty,is_active,main_image FROM products WHERE shop_id=? ORDER BY id DESC");
    $q->execute([$shopId]);
    echo json_encode(['ok' => true, 'items' => $q->fetchAll()]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
