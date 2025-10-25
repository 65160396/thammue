<?php
// /page/backend/admin/login.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string)($input['email'] ?? ''));
$pw    = (string)($input['password'] ?? '');
if ($email === '' || $pw === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing']); exit; }

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$st = $pdo->prepare("SELECT * FROM admin_users WHERE email=? LIMIT 1");
$st->execute([$email]);
$u = $st->fetch();
if (!$u || !password_verify($pw, $u['password_hash'])) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid']); exit;
}

$_SESSION['admin_id'] = (int)$u['id'];
$_SESSION['admin_name'] = $u['display_name'] ?: $u['email'];
echo json_encode(['ok'=>true, 'admin'=>['id'=>(int)$u['id'], 'name'=>($_SESSION['admin_name'])]]);
