<?php
// page/backend/config.php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';          // XAMPP ค่า default ว่าง
$DB_NAME = 'shopdb';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    http_response_code(500);
    die("DB connect failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
