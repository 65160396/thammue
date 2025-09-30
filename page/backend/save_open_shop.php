<?php
session_start();
require __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$shop_name   = trim($_POST['shop_name']   ?? '');
$pickup_addr = trim($_POST['pickup_addr'] ?? '');
$email       = trim($_POST['email']       ?? '');
$phone       = trim($_POST['phone']       ?? '');

if ($shop_name === '' || $pickup_addr === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{9,10}$/', $phone)) {
    exit('❌ ข้อมูลร้านไม่ถูกต้อง/ไม่ครบ');
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
} // dev only
$user_id = (int)$_SESSION['user_id'];

try {
    $conn->begin_transaction();

    /* ---- UPSERT: shops (unique: user_id) ---- */
    $sql = "INSERT INTO shops (user_id,shop_name,pickup_addr,email,phone,status,created_at)
          VALUES (?,?,?,?,?,'pending',NOW())
          ON DUPLICATE KEY UPDATE
            shop_name=VALUES(shop_name),
            pickup_addr=VALUES(pickup_addr),
            email=VALUES(email),
            phone=VALUES(phone),
            status='pending',
            updated_at=NOW()";
    $st = $conn->prepare($sql);
    $st->bind_param('issss', $user_id, $shop_name, $pickup_addr, $email, $phone);
    $st->execute();
    $st->close();

    /* ได้ shop_id ทั้งกรณี insert/update */
    $shop_id = $conn->insert_id;
    if ($shop_id == 0) {
        $q = $conn->prepare("SELECT id FROM shops WHERE user_id=? LIMIT 1");
        $q->bind_param('i', $user_id);
        $q->execute();
        $q->bind_result($shop_id);
        $q->fetch();
        $q->close();
    }

    /* ---- UPSERT: shop_verifications (unique: shop_id) ---- */
    $seller_type = $_POST['seller_type'] ?? '';

    if ($seller_type === 'person') {
        $citizen_name = trim($_POST['citizen_name'] ?? '');
        $citizen_id   = trim($_POST['citizen_id'] ?? '');
        $dob          = $_POST['dob_iso'] ?? null;
        $addr_line    = trim($_POST['addr_line'] ?? '');
        $subdistrict  = trim($_POST['subdistrict'] ?? '');
        $district     = trim($_POST['district'] ?? '');
        $province     = trim($_POST['province'] ?? '');
        $postcode     = trim($_POST['postcode'] ?? '');

        $st2 = $conn->prepare("
      INSERT INTO shop_verifications
        (shop_id,seller_type,citizen_name,citizen_id,dob,addr_line,subdistrict,district,province,postcode,status,created_at)
      VALUES
        (?,?,?,?,?,?,?,?,?,?,'pending',NOW())
      ON DUPLICATE KEY UPDATE
        seller_type=VALUES(seller_type),
        citizen_name=VALUES(citizen_name),
        citizen_id=VALUES(citizen_id),
        dob=VALUES(dob),
        addr_line=VALUES(addr_line),
        subdistrict=VALUES(subdistrict),
        district=VALUES(district),
        province=VALUES(province),
        postcode=VALUES(postcode),
        status='pending',
        updated_at=NOW()
    ");
        $st2->bind_param(
            'isssssssss',
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
        $st2->execute();
        $st2->close();
    } elseif ($seller_type === 'company') {
        $company_name = trim($_POST['company_name'] ?? '');
        $tax_id       = trim($_POST['tax_id'] ?? '');

        $st2 = $conn->prepare("
      INSERT INTO shop_verifications
        (shop_id,seller_type,company_name,tax_id,status,created_at)
      VALUES
        (?,?,?,?,'pending',NOW())
      ON DUPLICATE KEY UPDATE
        seller_type=VALUES(seller_type),
        company_name=VALUES(company_name),
        tax_id=VALUES(tax_id),
        status='pending',
        updated_at=NOW()
    ");
        $st2->bind_param('isss', $shop_id, $seller_type, $company_name, $tax_id);
        $st2->execute();
        $st2->close();
    }

    $conn->commit();

    /* PRG: กัน POST ซ้ำ */
    header('Location: /page/success_open_shop.html');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('DB Error: ' . $e->getMessage());
}
