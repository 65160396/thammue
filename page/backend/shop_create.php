<?php
session_start();

$dsn  = "mysql:host=127.0.0.1;dbname=shopdb;charset=utf8mb4";
$dbu  = "root";
$dbp  = "";  // แก้ให้ตรงกับเครื่องของผู้ใช้
// ✅ ฟังก์ชันช่วย: ตรวจเงื่อนไข ถ้าไม่ผ่านให้หยุดและแจ้ง error
function must($cond, $msg)
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}
// ✅ ฟังก์ชันบันทึกไฟล์ที่อัปโหลด (ใช้กับรูปบัตรหรือเอกสารยืนยัน)
function saveUpload($key, $dir, $basename)
{
       // ถ้าไม่ได้อัปโหลดไฟล์ → ข้าม
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
     // ตรวจสถานะอัปโหลด
    must($_FILES[$key]['error'] === UPLOAD_ERR_OK, "อัปโหลดไฟล์ผิดพลาด ($key)");
    must($_FILES[$key]['size'] <= 5 * 1024 * 1024, "ไฟล์ $key เกิน 5MB");
  // ตรวจชนิดไฟล์ (อนุญาตเฉพาะ JPG, PNG, PDF)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$key]['tmp_name']);
    $ext  = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
        default => null
    };
    must($ext !== null, "ชนิดไฟล์ $key ต้องเป็น JPG/PNG/PDF");

    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $basename . '_' . date('Ymd_His') . '.' . $ext;
    $to   = rtrim($dir, '/') . '/' . $name;
    move_uploaded_file($_FILES[$key]['tmp_name'], $to);
    return $to; // เก็บ path จริงไว้ใน DB (หรือจะเก็บแบบ relative ก็ได้)
}

try {
    $pdo = new PDO($dsn, $dbu, $dbp, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

     // ===== Step 1: เก็บข้อมูลพื้นฐานของร้าน =====
    $shop_name   = trim($_POST['shop_name']   ?? '');
    $pickup_addr = trim($_POST['pickup_addr'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');

    must($shop_name !== '' && $pickup_addr !== '' && $phone !== '', 'ข้อมูลร้านไม่ครบ');
    must(filter_var($email, FILTER_VALIDATE_EMAIL), 'อีเมลไม่ถูกต้อง');

    // หา user_id (จาก session ถ้ามี ไม่มีก็หาโดยอีเมล)
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $q->execute([$email]);
        $u = $q->fetch();
        must($u, 'ไม่พบผู้ใช้ในระบบ กรุณาล็อกอิน');
        $user_id = (int)$u['id'];
    }

     // ✅ บันทึก/อัปเดตร้าน (upsert)
    $up = $pdo->prepare("
    INSERT INTO shops (user_id, shop_name, pickup_addr, email, phone)
    VALUES (:uid,:sn,:addr,:em,:ph)
    ON DUPLICATE KEY UPDATE
      shop_name=VALUES(shop_name),
      pickup_addr=VALUES(pickup_addr),
      email=VALUES(email),
      phone=VALUES(phone),
      updated_at=CURRENT_TIMESTAMP
  ");
    $up->execute([':uid' => $user_id, ':sn' => $shop_name, ':addr' => $pickup_addr, ':em' => $email, ':ph' => $phone]);

   // ✅ ดึง shop_id ของร้านนั้น
    $sid = $pdo->prepare("SELECT id FROM shops WHERE user_id = ?");
    $sid->execute([$user_id]);
    $shop = $sid->fetch();
    must($shop, 'ไม่พบร้านที่เพิ่งบันทึก');
    $shop_id = (int)$shop['id'];

     // ===== Step 2: เก็บข้อมูลยืนยันตัวตนผู้ขาย (บุคคลหรือบริษัท) =====
    $sellerType = $_POST['sellerType'] ?? null;
    must(in_array($sellerType, ['person', 'company'], true), 'กรุณาเลือกประเภทผู้ขาย');

    $baseDir = __DIR__ . "/../../uploads/shops/$shop_id/verify";

    // ✅ เตรียมโครงข้อมูลสำหรับ insert/update
    $ver = [
        'seller_type'  => $sellerType,
        'citizen_name' => null,
        'citizen_id'   => null,
        'company_name' => null,
        'tax_id'       => null,
        'id_front'     => null,
        'id_back'      => null,
        'reg_doc'      => null,
        'id_rep'       => null,
    ];
// ✅ แยกกรณี: บุคคลธรรมดา
    if ($sellerType === 'person') {
        $ver['citizen_name'] = trim($_POST['citizenName'] ?? '');
        $ver['citizen_id']   = trim($_POST['citizenId'] ?? '');
        must($ver['citizen_name'] !== '', 'กรุณากรอกชื่อ-นามสกุล');
        must(preg_match('/^\d{13}$/', $ver['citizen_id']), 'เลขบัตรประชาชนไม่ถูกต้อง');
  // อัปโหลดรูปบัตรประชาชน หน้า-หลัง
        $ver['id_front'] = saveUpload('idFront', $baseDir, 'id_front');
        $ver['id_back']  = saveUpload('idBack',  $baseDir, 'id_back');
        must($ver['id_front'] && $ver['id_back'], 'กรุณาแนบรูปบัตรประชาชน');

         // ✅ หรือถ้าเป็นนิติบุคคล
    } else { 
        $ver['company_name'] = trim($_POST['companyName'] ?? '');
        $ver['tax_id']       = trim($_POST['taxId'] ?? '');
        must($ver['company_name'] !== '', 'กรุณากรอกชื่อบริษัท');
        must(preg_match('/^\d{10,13}$/', $ver['tax_id']), 'เลขผู้เสียภาษีไม่ถูกต้อง');
    // แนบเอกสารจดทะเบียน + บัตรผู้แทน
        $ver['reg_doc'] = saveUpload('regDoc', $baseDir, 'reg_doc');
        $ver['id_rep']  = saveUpload('idRep',  $baseDir, 'id_rep');
        must($ver['reg_doc'] && $ver['id_rep'], 'กรุณาแนบเอกสารนิติบุคคล');
    }

    // ✅ บันทึกข้อมูลยืนยันตัวตนลงตาราง shop_verifications (upsert)
    $sv = $pdo->prepare("
    INSERT INTO shop_verifications
      (shop_id, seller_type, citizen_name, citizen_id, company_name, tax_id,
       id_front_path, id_back_path, reg_doc_path, id_rep_path)
    VALUES
      (:sid, :type, :cname, :cid, :coname, :tax,
       :f1, :f2, :d1, :d2)
    ON DUPLICATE KEY UPDATE
      seller_type = VALUES(seller_type),
      citizen_name=VALUES(citizen_name),
      citizen_id  =VALUES(citizen_id),
      company_name=VALUES(company_name),
      tax_id      =VALUES(tax_id),
      id_front_path=COALESCE(VALUES(id_front_path), id_front_path),
      id_back_path =COALESCE(VALUES(id_back_path),  id_back_path),
      reg_doc_path =COALESCE(VALUES(reg_doc_path),  reg_doc_path),
      id_rep_path  =COALESCE(VALUES(id_rep_path),   id_rep_path),
      updated_at   =CURRENT_TIMESTAMP
  ");
    $sv->execute([
        ':sid' => $shop_id,
        ':type' => $ver['seller_type'],
        ':cname' => $ver['citizen_name'],
        ':cid' => $ver['citizen_id'],
        ':coname' => $ver['company_name'],
        ':tax' => $ver['tax_id'],
        ':f1' => $ver['id_front'],
        ':f2' => $ver['id_back'],
        ':d1' => $ver['reg_doc'],
        ':d2' => $ver['id_rep'],
    ]);

       // ✅ เสร็จแล้วเปลี่ยนหน้าไปหน้าสำเร็จ
    header('Location: /verify_shop.php?ok=1');
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo "❌ " . $e->getMessage();
}


must(preg_match('/^\d{9,10}$/', $phone), 'เบอร์โทรต้องเป็นตัวเลข 9–10 หลัก');
