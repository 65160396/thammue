<?php
session_start();
require __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

/* ---------- helper: เดาจังหวัดจากที่อยู่ (เร็ว ๆ ใช้งานได้จริงพอประมาณ) ---------- */
function guessProvinceFromAddress(?string $addr): ?string
{
  if (!$addr) return null;
  $addr = trim($addr);
  $provinces = [
    'กรุงเทพมหานคร',
    'กระบี่',
    'กาญจนบุรี',
    'กาฬสินธุ์',
    'กำแพงเพชร',
    'ขอนแก่น',
    'จันทบุรี',
    'ฉะเชิงเทรา',
    'ชลบุรี',
    'ชัยนาท',
    'ชัยภูมิ',
    'ชุมพร',
    'เชียงใหม่',
    'เชียงราย',
    'ตรัง',
    'ตราด',
    'ตาก',
    'นครนายก',
    'นครปฐม',
    'นครพนม',
    'นครราชสีมา',
    'นครศรีธรรมราช',
    'นครสวรรค์',
    'นราธิวาส',
    'น่าน',
    'นนทบุรี',
    'บึงกาฬ',
    'บุรีรัมย์',
    'ปทุมธานี',
    'ประจวบคีรีขันธ์',
    'ปราจีนบุรี',
    'ปัตตานี',
    'พระนครศรีอยุธยา',
    'พะเยา',
    'พังงา',
    'พัทลุง',
    'พิจิตร',
    'พิษณุโลก',
    'เพชรบุรี',
    'เพชรบูรณ์',
    'แพร่',
    'ภูเก็ต',
    'มหาสารคาม',
    'มุกดาหาร',
    'แม่ฮ่องสอน',
    'ยโสธร',
    'ยะลา',
    'ร้อยเอ็ด',
    'ระนอง',
    'ระยอง',
    'ราชบุรี',
    'ลพบุรี',
    'ลำปาง',
    'ลำพูน',
    'ศรีสะเกษ',
    'สกลนคร',
    'สงขลา',
    'สตูล',
    'สมุทรปราการ',
    'สมุทรสงคราม',
    'สมุทรสาคร',
    'สระแก้ว',
    'สระบุรี',
    'สิงห์บุรี',
    'สุโขทัย',
    'สุพรรณบุรี',
    'สุราษฎร์ธานี',
    'สุรินทร์',
    'หนองคาย',
    'หนองบัวลำภู',
    'อ่างทอง',
    'อุดรธานี',
    'อุตรดิตถ์',
    'อุทัยธานี',
    'อุบลราชธานี',
    'อำนาจเจริญ',
    'พรหมพิราม',
    'พะเยา'
  ];
  foreach ($provinces as $pv) {
    if (mb_strpos($addr, $pv) !== false) return $pv;
    if (mb_strpos($addr, "จังหวัด$pv") !== false) return $pv;
    if (mb_strpos($addr, "จ.$pv") !== false) return $pv;
  }
  return null;
}

/* ---------- รับค่าจากฟอร์ม ---------- */
$shop_name   = trim($_POST['shop_name']   ?? '');
$pickup_addr = trim($_POST['pickup_addr'] ?? '');
$email       = trim($_POST['email']       ?? '');
$phone       = trim($_POST['phone']       ?? '');
$province    = trim($_POST['province']    ?? '');     // ← เพิ่มรับจังหวัดจากฟอร์ม

if ($shop_name === '' || $pickup_addr === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{9,10}$/', $phone)) {
  exit('❌ ข้อมูลร้านไม่ถูกต้อง/ไม่ครบ');
}

/* ถ้าไม่ส่งจังหวัดมา พยายามเดาจากที่อยู่ */
if ($province === '') {
  $province = guessProvinceFromAddress($pickup_addr) ?? '';
}

/* DEV ONLY (ลบออกในโปรดักชัน) */
if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
}
$user_id = (int)$_SESSION['user_id'];

try {
  $conn->begin_transaction();

  /* ---- UPSERT: shops (unique: user_id) ---- */
  $sql = "INSERT INTO shops (user_id, shop_name, pickup_addr, province, email, phone, status, created_at, updated_at)
          VALUES (?,?,?,?,?,?,'pending',NOW(),NOW())
          ON DUPLICATE KEY UPDATE
            shop_name=VALUES(shop_name),
            pickup_addr=VALUES(pickup_addr),
            province=VALUES(province),
            email=VALUES(email),
            phone=VALUES(phone),
            status='pending',
            updated_at=NOW()";
  $st = $conn->prepare($sql);
  // i s s s s s
  $st->bind_param('isssss', $user_id, $shop_name, $pickup_addr, $province, $email, $phone);
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

  /* ---- UPSERT: shop_verifications (optional) ---- */
  $seller_type = $_POST['seller_type'] ?? '';

  if ($seller_type === 'person') {
    $citizen_name = trim($_POST['citizen_name'] ?? '');
    $citizen_id   = trim($_POST['citizen_id'] ?? '');
    $dob          = $_POST['dob_iso'] ?? null;
    $addr_line    = trim($_POST['addr_line'] ?? '');
    $subdistrict  = trim($_POST['subdistrict'] ?? '');
    $district     = trim($_POST['district'] ?? '');
    $verify_province = trim($_POST['province'] ?? '');   // ← ใช้ชื่อแยกต่างหาก
    $postcode     = trim($_POST['postcode'] ?? '');

    $st2 = $conn->prepare("
      INSERT INTO shop_verifications
        (shop_id,seller_type,citizen_name,citizen_id,dob,addr_line,subdistrict,district,province,postcode,status,created_at,updated_at)
      VALUES
        (?,?,?,?,?,?,?,?,?,?,'pending',NOW(),NOW())
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
      $verify_province,
      $postcode
    );
    $st2->execute();
    $st2->close();

    /* ถ้า shops.province ยังว่าง แต่แบบยืนยันมี ให้ sync กลับ */
    if ($province === '' && $verify_province !== '') {
      $upPv = $conn->prepare("UPDATE shops SET province=?, updated_at=NOW() WHERE id=?");
      $upPv->bind_param('si', $verify_province, $shop_id);
      $upPv->execute();
      $upPv->close();
      $province = $verify_province; // อัปเดตตัวแปรในสคริปต์ด้วย
    }
  } elseif ($seller_type === 'company') {
    $company_name = trim($_POST['company_name'] ?? '');
    $tax_id       = trim($_POST['tax_id'] ?? '');

    $st2 = $conn->prepare("
      INSERT INTO shop_verifications
        (shop_id,seller_type,company_name,tax_id,status,created_at,updated_at)
      VALUES
        (?,?,?,?,'pending',NOW(),NOW())
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

  /* ---- อัปโหลดรูปโปรไฟล์ร้าน (ออปชัน) ---- */
  if (!empty($_FILES['shop_avatar']['tmp_name']) && $_FILES['shop_avatar']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['shop_avatar']['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allow = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (isset($allow[$mime])) {
      if ($_FILES['shop_avatar']['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Avatar too large (>3MB)');
      }

      $ext = $allow[$mime];

      $root  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2), '/');
      $dirRel = "/page/uploads/shops/{$shop_id}";
      $dirAbs = $root . $dirRel;
      if (!is_dir($dirAbs)) {
        mkdir($dirAbs, 0777, true);
      }

      foreach (glob($dirAbs . "/avatar.*") as $old) {
        @unlink($old);
      }

      $destAbs = $dirAbs . "/avatar." . $ext;
      if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException('Cannot move uploaded avatar');
      }

      $avatarRel = $dirRel . "/avatar." . $ext;

      $up = $conn->prepare("UPDATE shops SET avatar_path=?, updated_at=NOW() WHERE id=?");
      $up->bind_param('si', $avatarRel, $shop_id);
      $up->execute();
      $up->close();
    }
  }

  $conn->commit();

  // ไปหน้าสำเร็จ (หรือจะเปลี่ยนเป็นไปหน้าร้านก็ได้)
  // header('Location: /page/store/store_public.php?id=' . (int)$shop_id);
  header('Location: /page/success_open_shop.html');
  exit;
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('DB Error: ' . $e->getMessage());
}
