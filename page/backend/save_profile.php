<?php
session_start();
require_once __DIR__ . '/config.php'; // มี $conn = new mysqli(...);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['user_id'])) {
  header('Location: /page/login.html?msg=โปรดเข้าสู่ระบบ&type=error'); exit;
}

$uid   = (int)$_SESSION['user_id'];
$first = trim($_POST['first_name'] ?? '');
$last  = trim($_POST['last_name']  ?? '');
$phone = trim($_POST['phone']      ?? '');
$gender= $_POST['gender']          ?? null;
$dob   = $_POST['dob']             ?? null; // YYYY-MM-DD จาก hidden dobIso

$addr_line        = trim($_POST['addr_line']       ?? '');
$addr_province    = $_POST['addr_province']        ?? null;
$addr_district    = $_POST['addr_district']        ?? null;
$addr_subdistrict = $_POST['addr_subdistrict']     ?? null;
$addr_postcode    = $_POST['addr_postcode']        ?? null;

// upsert (มีแล้วอัปเดต, ไม่มีให้แทรก)
$sql = "
INSERT INTO user_profiles
  (user_id, first_name, last_name, phone, gender, dob,
   addr_line, addr_province, addr_district, addr_subdistrict, addr_postcode)
VALUES (?,?,?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
  first_name=VALUES(first_name),
  last_name=VALUES(last_name),
  phone=VALUES(phone),
  gender=VALUES(gender),
  dob=VALUES(dob),
  addr_line=VALUES(addr_line),
  addr_province=VALUES(addr_province),
  addr_district=VALUES(addr_district),
  addr_subdistrict=VALUES(addr_subdistrict),
  addr_postcode=VALUES(addr_postcode)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'issssssssss',
  $uid, $first, $last, $phone, $gender, $dob,
  $addr_line, $addr_province, $addr_district, $addr_subdistrict, $addr_postcode
);
$ok = $stmt->execute();
$stmt->close();

// (ออปชัน) sync ชื่อโชว์เดิมใน users.name ให้เป็น "ชื่อ นามสกุล"
if ($ok) {
  $display = trim($first.' '.$last);
  if ($display !== '') {
    $up = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $up->bind_param('si', $display, $uid);
    $up->execute();
    $up->close();
  }
}

$conn->close();
header('Location: /page/profile.html?type='.($ok?'success':'error').
       '&msg='.rawurlencode($ok?'บันทึกข้อมูลเรียบร้อย':'บันทึกไม่สำเร็จ'));
exit;