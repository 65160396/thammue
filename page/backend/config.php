<?php
// page/backend/config.php

// 1) รายงาน error ของ mysqli เป็น Exception (ดีเวลลอปเท่านั้น)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';          // XAMPP default
$DB_NAME = 'shopdb';

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');

    // (ทางเลือก) บังคับโหมด SQL เข้มงวดขึ้น
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
    $conn->query("SET time_zone = '+07:00'"); // ตามโซนไทย

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    // ในโปรดักชัน ควร log แล้วแสดงข้อความกลางๆ ไม่โชว์รายละเอียด
    echo "Database connection error.";
    exit;
}
