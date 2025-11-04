<?php
// /page/backend/change_password.php (MySQLi)
// ✅ หน้าที่ของไฟล์นี้: ใช้สำหรับให้ผู้ใช้เปลี่ยนรหัสผ่านของตัวเองอย่างปลอดภัย
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);
// ✅ เรียกไฟล์ _guard.php ที่มีการเชื่อมต่อฐานข้อมูล + session + ฟังก์ชัน me() (คืนค่า user_id)
require_once __DIR__ . '/_guard.php'; // มี config.php + session + me()

$uid = (int) me();
if (!$uid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

// รองรับ JSON body
$raw = file_get_contents('php://input');
if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $_POST = json_decode($raw, true) ?: [];
}
// ✅ รับค่ารหัสผ่านเดิม / ใหม่ / ยืนยัน
$curr = trim($_POST['current_password'] ?? '');
$new  = trim($_POST['new_password'] ?? '');
$conf = trim($_POST['confirm_password'] ?? '');
// ✅ ตรวจความครบของฟิลด์
if ($curr === '' || $new === '' || $conf === '') {
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}
// ✅ ตรวจความยาวขั้นต่ำ
if (strlen($new) < 8) {
    echo json_encode(['ok' => false, 'error' => 'too_short', 'min' => 8]);
    exit;
}
// ✅ ตรวจว่ารหัสใหม่กับยืนยันตรงกันไหม
if ($new !== $conf) {
    echo json_encode(['ok' => false, 'error' => 'confirm_mismatch']);
    exit;
}

try {
    $mysqli = db(); // <-- MySQLi

    // ✅ ดึงรหัสผ่านปัจจุบันจากฐานข้อมูล
    $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($password_hash);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit;
    }
    // ✅ ตรวจว่ารหัสผ่านปัจจุบันถูกต้องไหม
    if (!password_verify($curr, (string)$password_hash)) {
        echo json_encode(['ok' => false, 'error' => 'current_incorrect']);
        exit;
    }
     // ✅ ป้องกันไม่ให้เปลี่ยนเป็นรหัสเดิม
    if (password_verify($new, (string)$password_hash)) {
        echo json_encode(['ok' => false, 'error' => 'same_as_old']);
        exit;
    }

    // อัปเดต hash ใหม่
    $newHash = password_hash($new, PASSWORD_BCRYPT);

    $up = $mysqli->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?");
    $up->bind_param("si", $newHash, $uid);
    $up->execute();
    $affected = $up->affected_rows;
    $up->close();

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    echo json_encode(['ok' => true, 'affected' => $affected]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
