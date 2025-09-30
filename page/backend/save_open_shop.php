<?php
session_start();
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* รับค่า POST */
$shop_name   = trim($_POST['shop_name']   ?? '');
$pickup_addr = trim($_POST['pickup_addr'] ?? '');
$email       = trim($_POST['email']       ?? '');
$phone       = trim($_POST['phone']       ?? '');

/* ตรวจความถูกต้องเบื้องต้น */
if ($shop_name === '' || $pickup_addr === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{9,10}$/', $phone)) {
    exit('❌ ข้อมูลร้านไม่ถูกต้อง/ไม่ครบ');
}

/* ระหว่างพัฒนา: ถ้ายังไม่มี session ให้ใส่ค่าให้ไม่เป็น NULL (ลบออกเมื่อทำระบบล็อกอินจริง) */
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // <-- dev only
}
$user_id = (int)$_SESSION['user_id'];

/* UPSERT: ถ้ายังไม่มีร้านสำหรับ user_id นี้ -> INSERT, ถ้ามีแล้ว -> UPDATE */
$sql = "
  INSERT INTO shops (user_id, shop_name, pickup_addr, email, phone, status, created_at)
  VALUES (?,?,?,?,?,'pending', NOW())
  ON DUPLICATE KEY UPDATE
    shop_name   = VALUES(shop_name),
    pickup_addr = VALUES(pickup_addr),
    email       = VALUES(email),
    phone       = VALUES(phone),
    updated_at  = NOW()
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('issss', $user_id, $shop_name, $pickup_addr, $email, $phone);
$stmt->execute();
$stmt->close();

/* ดึง shop_id กลับมาให้ได้ทั้งกรณี INSERT และ UPDATE */
$shop_id = $conn->insert_id;   // ถ้าเป็น UPDATE มักจะได้ 0
if ($shop_id == 0) {
    $q = $conn->prepare("SELECT id FROM shops WHERE user_id = ? LIMIT 1");
    $q->bind_param('i', $user_id);
    $q->execute();
    $q->bind_result($shop_id);
    $q->fetch();
    $q->close();
}

/* ===== หลังจาก INSERT shops แล้ว และมี $shop_id ===== */

$seller_type = $_POST['seller_type'] ?? '';

if ($seller_type === 'person') {
    // ฟิลด์ฝั่งบุคคลธรรมดา
    $citizen_name = trim($_POST['citizen_name'] ?? '');
    $citizen_id   = trim($_POST['citizen_id'] ?? '');
    $dob          = $_POST['dob_iso'] ?? null;  // YYYY-MM-DD ที่เราสร้างไว้
    $addr_line    = trim($_POST['addr_line'] ?? '');
    $subdistrict  = trim($_POST['subdistrict'] ?? '');
    $district     = trim($_POST['district'] ?? '');
    $province     = trim($_POST['province'] ?? '');
    $postcode     = trim($_POST['postcode'] ?? '');

    $stmt2 = $conn->prepare("
        INSERT INTO shop_verifications
            (shop_id, seller_type, citizen_name, citizen_id, dob,
             addr_line, subdistrict, district, province, postcode, status, created_at)
        VALUES
            (?,       ?,           ?,            ?,          ?,
             ?,         ?,          ?,        ?,        ?,       'pending', NOW())
    ");
    $stmt2->bind_param(
        "isssssssss",
        $shop_id,
        $seller_type,
        $citizen_name,
        $citizen_id,
        $dob,
        $addr_line,
        $subdistrict,
        $district,
        $province,
        $postcode
    );
    $stmt2->execute();
    $stmt2->close();
} elseif ($seller_type === 'company') {
    // ฟิลด์ฝั่งนิติบุคคล
    $company_name = trim($_POST['company_name'] ?? '');
    $tax_id       = trim($_POST['tax_id'] ?? '');

    $stmt2 = $conn->prepare("
        INSERT INTO shop_verifications
            (shop_id, seller_type, company_name, tax_id, status, created_at)
        VALUES
            (?,       ?,           ?,            ?,     'pending', NOW())
    ");
    $stmt2->bind_param("isss", $shop_id, $seller_type, $company_name, $tax_id);
    $stmt2->execute();
    $stmt2->close();
}


/* เสร็จแล้วพาไปหน้า success */
header('Location: ../success.html');
exit;
