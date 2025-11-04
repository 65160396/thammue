<?php
// /page/backend/products/get_recommended.php
// ✅ หน้าที่ของไฟล์นี้: ใช้เป็น API สำหรับ “ดึงรายการสินค้าแนะนำ (Recommended Products)”
// โดยจะดึงสินค้าจำนวนหนึ่ง (เช่น 12 ชิ้นล่าสุด) จากฐานข้อมูล shopdb
// เพื่อไปแสดงในหน้าแรก หรือหน้า “สินค้าแนะนำ” ของเว็บไซต์
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

/*  เงื่อนไขหลักของการดึงข้อมูล (สามารถปรับได้ตามต้องการ)
ปรับเกณฑ์ได้:
   - ORDER BY created_at DESC   (ดึงสินค้าที่สร้างล่าสุดก่อน)
   - LIMIT 12                   (จำกัดจำนวนแค่ 12 ชิ้น)
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
