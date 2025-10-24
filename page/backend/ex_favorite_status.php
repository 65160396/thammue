<?php
// /page/backend/ex_favorite_status.php
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $m = dbx();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $uid = me();
    if (!$uid) {
        echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
        exit;
    }

    $product_id = (int)($_GET['item_id'] ?? 0);
    if ($product_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_item']);
        exit;
    }

    $st = $m->prepare("SELECT 1 FROM ex_favorites WHERE user_id=? AND product_id=? LIMIT 1");
    $st->bind_param('ii', $uid, $product_id);
    $st->execute();
    $isFav = (bool)$st->get_result()->fetch_row();

    echo json_encode(['ok' => true, 'is_favorite' => $isFav]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'fatal: ' . $e->getMessage()]);
}
