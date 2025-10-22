<?php
// /page/backend/_guard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ใช้ร่วมกับ /page/backend/config.php ที่เปิด mysqli และตั้ง charset/timezone แล้ว
require_once __DIR__ . '/config.php';

// สมมุติระบบตั้ง user_id ไว้ใน session หลัง login
$CURRENT_UID = $_SESSION['user_id'] ?? 0;
if (!$CURRENT_UID) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
