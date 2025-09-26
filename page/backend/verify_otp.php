<?php
// THAMMUE/backend/verify_otp.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/config.php';

use PDO, DateTime;

$dbcfg = $config['db'];
$pdo = new PDO(
    "mysql:host={$dbcfg['host']};dbname={$dbcfg['dbname']};charset={$dbcfg['charset']}",
    $dbcfg['user'],
    $dbcfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');
if (!$email || !$otp) exit('ข้อมูลไม่ครบ');

// หา user
$st = $pdo->prepare("SELECT id,is_verified FROM users WHERE email=?");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) exit('ไม่พบอีเมลนี้');

// โหลด OTP ล่าสุด
$st = $pdo->prepare("SELECT * FROM email_verifications WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$st->execute([(int)$user['id']]);
$ver = $st->fetch(PDO::FETCH_ASSOC);
if (!$ver) exit('ไม่มีรหัส OTP หรือหมดอายุแล้ว');

// ตรวจหมดอายุ
if (new DateTime() > new DateTime($ver['expires_at'])) {
    $pdo->prepare("DELETE FROM email_verifications WHERE id=?")->execute([(int)$ver['id']]);
    exit('OTP หมดอายุแล้ว กรุณาขอใหม่');
}

// จำกัดจำนวนพยายาม
if ((int)$ver['attempts'] >= 5) exit('ลองมากเกินไป กรุณาขอ OTP ใหม่');

// เทียบรหัส
if (hash_equals($ver['otp_code'], $otp)) {
    $pdo->prepare("UPDATE users SET is_verified=1 WHERE id=?")->execute([(int)$user['id']]);
    $pdo->prepare("DELETE FROM email_verifications WHERE user_id=?")->execute([(int)$user['id']]);
    echo "ยืนยันสำเร็จ! ไปที่หน้า <a href=\"../page/login.html\">เข้าสู่ระบบ</a>";
} else {
    $pdo->prepare("UPDATE email_verifications SET attempts=attempts+1 WHERE id=?")
        ->execute([(int)$ver['id']]);
    exit('OTP ไม่ถูกต้อง');
}
