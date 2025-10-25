<?php
// /page/backend/admin/reports_list.php
require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/../ex__common.php';
require_admin();

$pdo = new PDO("mysql:host=".EX_DB_HOST.";dbname=".EX_DB_NAME.";charset=utf8mb4", EX_DB_USER, EX_DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
CREATE TABLE IF NOT EXISTS ex_item_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  user_id INT NULL,
  reason TEXT NOT NULL,
  status ENUM('open','resolved') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$sql = "SELECT r.*, i.title AS item_title
        FROM ex_item_reports r
        LEFT JOIN ex_items i ON i.id = r.item_id
        WHERE r.status='open'
        ORDER BY r.created_at DESC";
$st = $pdo->query($sql);
echo json_encode(['ok'=>true,'reports'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
