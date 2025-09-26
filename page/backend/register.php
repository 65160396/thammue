<?php
// THAMMUE/backend/register.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';       // จะมีหลังจาก composer install
$config = require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PDO, DateTime, DateInterval;

// เชื่อม DB
$dbcfg = $config['db'];
$pdo = new PDO(
    "mysql:host={$dbcfg['host']};dbname={$dbcfg['dbname']};charset={$dbcfg['charset']}",
    $dbcfg['user'],
    $dbcfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// รับค่า
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) exit('ข้อมูลไม่ครบ');

// เช็คอีเมลซ้ำ
$st = $pdo->prepare("SELECT id,is_verified FROM users WHERE email=?");
$st->execute([$email]);
$exist = $st->fetch(PDO::FETCH_ASSOC);
if ($exist && (int)$exist['is_verified'] === 1) exit('อีเมลนี้ถูกใช้งานแล้ว');

// สร้าง user ถ้ายังไม่มี
if (!$exist) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)")
        ->execute([$name, $email, $hash]);
    $user_id = (int)$pdo->lastInsertId();
} else {
    $user_id = (int)$exist['id'];
}

// สร้าง OTP
$len = (int)$config['otp']['length'];
$otp = str_pad((string)random_int(0, (int)str_repeat('9', $len)), $len, '0', STR_PAD_LEFT);
$expires = (new DateTime())->add(new DateInterval('PT' . $config['otp']['expire_minutes'] . 'M'))
    ->format('Y-m-d H:i:s');

// เก็บ OTP (ลบของเก่าก่อน)
$pdo->prepare("DELETE FROM email_verifications WHERE user_id=?")->execute([$user_id]);
$pdo->prepare("INSERT INTO email_verifications (user_id, otp_code, expires_at) VALUES (?,?,?)")
    ->execute([$user_id, $otp, $expires]);

// ส่งอีเมล
try {
    $s = $config['smtp'];
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $s['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $s['username'];
    $mail->Password = $s['password'];
    $mail->SMTPSecure = $s['secure'];
    $mail->Port = (int)$s['port'];
    $mail->setFrom($s['from_email'], $s['from_name']);
    $mail->addAddress($email, $name ?: $email);
    $mail->isHTML(true);
    $mail->Subject = 'OTP สำหรับยืนยันอีเมล';
    $mail->Body = "<p>สวัสดี " . htmlspecialchars($name ?: $email) . "</p>
                 <p>รหัส OTP ของคุณคือ <strong>" . htmlspecialchars($otp) . "</strong></p>
                 <p>รหัสหมดอายุใน {$config['otp']['expire_minutes']} นาที</p>";
    $mail->send();
    echo "ส่ง OTP ไปแล้ว กรุณาตรวจอีเมล แล้วไปที่หน้า <a href=\"../page/verify_otp.html\">ยืนยัน OTP</a>.";
} catch (\Throwable $e) {
    http_response_code(500);
    echo "ส่งอีเมลไม่สำเร็จ: " . htmlspecialchars($e->getMessage());
}
