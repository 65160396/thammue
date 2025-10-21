<?php
session_start();
require __DIR__ . '/../backend/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('unauthorized');
    $pid = (int)$_POST['product_id'];
    $sid = (int)$_POST['shop_id'];
    $uid = (int)$_SESSION['user_id'];

    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $chk = $pdo->prepare("SELECT p.id FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.id=? AND p.shop_id=? AND s.user_id=?");
    $chk->execute([$pid, $sid, $uid]);
    if (!$chk->fetch()) throw new Exception('forbidden');

    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
