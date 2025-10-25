<?php
// /page/backend/admin/reports_resolve.php
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/../ex__common.php';
require_admin();

$pdo = new PDO("mysql:host=".EX_DB_HOST.";dbname=".EX_DB_NAME.";charset=utf8mb4", EX_DB_USER, EX_DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$report_id = (int)($input['report_id'] ?? 0);
if ($report_id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }

$st = $pdo->prepare("UPDATE ex_item_reports SET status='resolved', resolved_at=NOW() WHERE id=?");
$st->execute([$report_id]);

echo json_encode(['ok'=>true]);
