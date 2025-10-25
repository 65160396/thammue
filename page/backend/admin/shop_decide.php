<?php
// /page/backend/admin/shop_decide.php
require_once __DIR__ . '/require_admin.php';
$pdo = admin_db();
require_admin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$shop_id = (int)($input['shop_id'] ?? 0);
$action  = trim((string)($input['action'] ?? ''));
$reason  = trim((string)($input['reason'] ?? ''));
if ($shop_id<=0 || !in_array($action,['approve','reject'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }

$pdo->beginTransaction();
try {
  if ($action === 'approve') {
    $st = $pdo->prepare("UPDATE shops SET status='approved', updated_at=NOW() WHERE id=?");
    $st->execute([$shop_id]);
    $st = $pdo->prepare("UPDATE shop_verifications SET status='approved', updated_at=NOW() WHERE shop_id=?");
    $st->execute([$shop_id]);
  } else {
    $st = $pdo->prepare("UPDATE shops SET status='rejected', updated_at=NOW() WHERE id=?");
    $st->execute([$shop_id]);
    $st = $pdo->prepare("UPDATE shop_verifications SET status='rejected', updated_at=NOW() WHERE shop_id=?");
    $st->execute([$shop_id]);
    // store reason to a simple table
    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_rejections (
      id INT AUTO_INCREMENT PRIMARY KEY,
      shop_id INT NOT NULL,
      reason TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $pdo->prepare("INSERT INTO shop_rejections (shop_id,reason) VALUES (?,?)");
    $st->execute([$shop_id, $reason]);
  }
  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_error']);
}
