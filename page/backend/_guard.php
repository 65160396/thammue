<?php
// /page/backend/_guard.php
header('Content-Type: application/json; charset=utf-8');

// (ดีบัก) ให้เห็น error ชัด ๆ
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ชี้ config.php ที่โฟลเดอร์เดียวกัน
require_once __DIR__ . '/config.php';  // ในนี้ต้องมีฟังก์ชัน db()

// ให้ชัวร์ว่าเริ่ม session แล้ว
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ประกาศฟังก์ชัน me() ให้ไฟล์อื่นเรียกใช้ได้
function me()
{
    return (int)($_SESSION['user_id'] ?? 0);
}

// ถ้าต้อง “บังคับให้ล็อกอินก่อน” ให้ใช้บล็อกนี้ในไฟล์ที่ต้องการ
// ถ้าไม่อยากบังคับที่ _guard.php ให้ย้ายไปเช็กในไฟล์ปลายทางแทนก็ได้
