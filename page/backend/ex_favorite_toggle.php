<?php
// /page/backend/ex_favorite_toggle.php
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

  $product_id = (int)($_POST['item_id'] ?? 0); // frontend ส่งชื่อ item_id มา
  if ($product_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'bad_item']);
    exit;
  }

  // ตรวจว่ามีรายการโปรดนี้อยู่แล้วไหม
  $st = $m->prepare("SELECT 1 FROM ex_favorites WHERE user_id=? AND product_id=? LIMIT 1");
  $st->bind_param('ii', $uid, $product_id);
  $st->execute();
  $exists = (bool)$st->get_result()->fetch_row();

  if ($exists) {
    // ลบออกจากรายการโปรด
    $st = $m->prepare("DELETE FROM ex_favorites WHERE user_id=? AND product_id=?");
    $st->bind_param('ii', $uid, $product_id);
    $st->execute();
    echo json_encode(['ok' => true, 'status' => 'removed', 'is_favorite' => false]);
  } else {
    // เพิ่มเข้าไปใหม่
    $st = $m->prepare("INSERT INTO ex_favorites (user_id, product_id, created_at) VALUES (?, ?, NOW())");
    $st->bind_param('ii', $uid, $product_id);
    $st->execute();
    echo json_encode(['ok' => true, 'status' => 'added', 'is_favorite' => true]);
  }
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => 'fatal: ' . $e->getMessage()]);
}
