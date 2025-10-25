<?php
// /page/backend/admin/seed_admin.php
// Run once to create an admin account. Change credentials immediately.
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$email = $_GET['email'] ?? 'admin@example.com';
$pass  = $_GET['password'] ?? 'changeme123';
$name  = $_GET['name'] ?? 'Administrator';

$hash = password_hash($pass, PASSWORD_DEFAULT);
$st = $pdo->prepare("INSERT INTO admin_users (email,password_hash,display_name) VALUES (?,?,?)");
$st->execute([$email, $hash, $name]);

echo "OK\nAdmin created: $email / $pass\nChange the password now.";
