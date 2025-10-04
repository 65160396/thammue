<?php
// /page/backend/exchange/exchange_categories.php
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$cats = [];
try {
    $q = $conn->query("SELECT id,name,parent_id FROM exchange_categories ORDER BY name ASC");
    $cats = $q->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    // fallback (กรณียังไม่ได้สร้างตาราง)
    $cats = [
        ['id' => 1, 'name' => 'อิเล็กทรอนิกส์', 'parent_id' => null],
        ['id' => 2, 'name' => 'เครื่องใช้ภายในบ้าน', 'parent_id' => null],
        ['id' => 3, 'name' => 'อุปกรณ์เด็ก', 'parent_id' => null],
        ['id' => 4, 'name' => 'เสื้อผ้า', 'parent_id' => null],
        ['id' => 5, 'name' => 'หนังสือ', 'parent_id' => null],
        ['id' => 6, 'name' => 'ของสะสม', 'parent_id' => null],
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'categories' => $cats], JSON_UNESCAPED_UNICODE);
