<?php
// /page/backend/admin/require_admin.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config.php'; // gives $conn (mysqli) and $DB_*

function admin_db() {
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function require_admin() {
  if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'admin_unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
header('Content-Type: application/json; charset=utf-8');
