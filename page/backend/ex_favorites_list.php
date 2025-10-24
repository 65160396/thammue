<?php
// /page/backend/ex_favorites_list.php
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

  // หา column รูปใน ex_items
  $imgCol = 'thumbnail_url';
  $check = $m->query("SHOW COLUMNS FROM ex_items LIKE 'image_url'");
  if ($check->num_rows) $imgCol = 'image_url';

  $sql = "
    SELECT f.product_id AS item_id,
           i.title,
           i.$imgCol AS thumb,
           f.created_at
    FROM ex_favorites f
    JOIN ex_items i ON i.id = f.product_id
    WHERE f.user_id=?
    ORDER BY f.created_at DESC
    LIMIT 200
  ";
  $st = $m->prepare($sql);
  $st->bind_param('i', $uid);
  $st->execute();
  $rs = $st->get_result();

  $out = [];
  while ($r = $rs->fetch_assoc()) {
    $out[] = [
      'item_id' => (int)$r['item_id'],
      'title'   => $r['title'],
      'thumb'   => $r['thumb'],
      'created_at' => $r['created_at']
    ];
  }

  echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => 'fatal: ' . $e->getMessage()]);
}
