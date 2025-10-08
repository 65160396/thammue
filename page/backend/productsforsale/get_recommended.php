<?php
// /page/backend/products/get_recommended.php
header('Content-Type: application/json; charset=utf-8');

$dsn  = 'mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'code' => 'DB_CONNECT_FAILED']);
    exit;
}

/* ปรับเกณฑ์ได้:
   - ORDER BY created_at DESC   (ล่าสุด)
   - LIMIT 12                   (จำนวนที่ต้องการ)
   - WHERE main_image IS NOT NULL   (เฉพาะที่มีรูป)
*/
$sql = "
  SELECT id, name, price, main_image
  FROM products
  WHERE main_image IS NOT NULL
  ORDER BY created_at DESC
  LIMIT 12
";
$rows = $pdo->query($sql)->fetchAll();

echo json_encode(['ok' => true, 'items' => $rows]);
