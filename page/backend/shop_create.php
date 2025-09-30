<?php
session_start();

$dsn  = "mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4";
$user = "root";
$pass = ""; // แก้ตามเครื่อง

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit("DB error: " . $e->getMessage());
}

// รับค่า Step 1
$shop_name   = trim($_POST['shop_name'] ?? '');
$pickup_addr = trim($_POST['pickup_addr'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');

// ตรวจสอบ
$errors = [];
if ($shop_name === '')   $errors[] = 'กรุณากรอกชื่อร้านค้า';
if ($pickup_addr === '') $errors[] = 'กรุณากรอกที่อยู่รับสินค้า';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
if ($phone === '')       $errors[] = 'กรุณากรอกเบอร์โทร';

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    header('Location: /open_a_shop.html'); // กลับไปหน้าฟอร์ม
    exit;
}

// หา user_id จาก session (แนะนำ) หรือจากอีเมล (สำรอง)
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $q->execute([$email]);
    $u = $q->fetch();
    if (!$u) {
        $_SESSION['form_errors'] = ['ไม่พบผู้ใช้ กรุณาล็อกอินก่อนเปิดร้าน'];
        header('Location: /login.php');
        exit;
    }
    $user_id = (int)$u['id'];
}

// บันทึก (1 user 1 ร้าน)
$sql = "INSERT INTO shops (user_id, shop_name, pickup_addr, email, phone)
        VALUES (:user_id, :shop_name, :pickup_addr, :email, :phone)
        ON DUPLICATE KEY UPDATE
          shop_name=VALUES(shop_name),
          pickup_addr=VALUES(pickup_addr),
          email=VALUES(email),
          phone=VALUES(phone),
          updated_at=CURRENT_TIMESTAMP";
$st = $pdo->prepare($sql);
$st->execute([
    ':user_id'     => $user_id,
    ':shop_name'   => $shop_name,
    ':pickup_addr' => $pickup_addr,
    ':email'       => $email,
    ':phone'       => $phone,
]);

// ไปหน้าขั้นถัดไป เช่น verify
header('Location: /verify_shop.php');
exit;
