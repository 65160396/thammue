<?php
// /page/backend/admin/kyc_list.php
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/../ex__common.php'; // defines EX_DB_* and helpers
require_admin();

$pdo = new PDO("mysql:host=".EX_DB_HOST.";dbname=".EX_DB_NAME.";charset=utf8mb4", EX_DB_USER, EX_DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$st = $pdo->query("
  SELECT id, user_id, full_name, dob, address, id_front_url, id_back_url, selfie_url, status, created_at
  FROM ex_user_kyc
  WHERE status='pending'
  ORDER BY created_at ASC
");
echo json_encode(['ok'=>true, 'kyc'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
