<?php
// /page/backend/admin/kyc_decide.php
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/../ex__common.php';
require_admin();

$pdo = new PDO("mysql:host=".EX_DB_HOST.";dbname=".EX_DB_NAME.";charset=utf8mb4", EX_DB_USER, EX_DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$kyc_id = (int)($input['kyc_id'] ?? 0);
$action = trim((string)($input['action'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));
if ($kyc_id<=0 || !in_array($action,['approve','reject'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }

$st = $pdo->prepare("SELECT * FROM ex_user_kyc WHERE id=?");
$st->execute([$kyc_id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

if ($action === 'approve') {
  $st = $pdo->prepare("UPDATE ex_user_kyc SET status='approved', updated_at=NOW() WHERE id=?");
  $st->execute([$kyc_id]);
} else {
  $st = $pdo->prepare("UPDATE ex_user_kyc SET status='rejected', updated_at=NOW() WHERE id=?");
  $st->execute([$kyc_id]);
  $pdo->exec("CREATE TABLE IF NOT EXISTS ex_user_kyc_rejections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kyc_id INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $st = $pdo->prepare("INSERT INTO ex_user_kyc_rejections (kyc_id,reason) VALUES (?,?)");
  $st->execute([$kyc_id, $reason]);
}

echo json_encode(['ok'=>true]);
