<?php
// page/backend/config.php
// ✅ ไฟล์นี้เป็น "ไฟล์ตั้งค่าการเชื่อมต่อฐานข้อมูลหลักของระบบ"
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// ✅ กำหนดค่าการเชื่อมต่อฐานข้อมูล
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';// รหัสผ่าน (ว่างถ้าใช้ XAMPP/MAMP)
$DB_NAME = 'shopdb';

try {
     // ✅ สร้างการเชื่อมต่อฐานข้อมูล (ใช้ mysqli)
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
    $conn->query("SET time_zone = '+07:00'");
} catch (mysqli_sql_exception $e) {
     // ❌ ถ้าเชื่อมต่อไม่สำเร็จ → แสดงข้อความและหยุดการทำงาน
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

/* ✅ ฟังก์ชันช่วย db()
   - ใช้เพื่อเรียก connection ตัวเดียวกันในไฟล์อื่น ๆ ได้ง่าย
   - แทนการเขียน global $conn ซ้ำ ๆ
*/
function db(): mysqli
{
    // ใช้ connection เดียวกันทั่วระบบ
    global $conn;
    return $conn;
}
