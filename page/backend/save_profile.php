<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  header('Location: /page/login.html?next=' . rawurlencode('/page/profile.html'));
  exit;
}
$userId = (int)$_SESSION['user_id'];

/* DB */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* รับค่า */
$first  = trim($_POST['first_name'] ?? '');
$last   = trim($_POST['last_name'] ?? '');
$phone  = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
$gender = $_POST['gender'] ?? null;                  // 'male' | 'female' | 'other' | 'prefer_not'
$dob    = $_POST['dob'] ?? '';                       // คาดว่าเป็น YYYY-MM-DD

$prov   = trim($_POST['addr_province'] ?? '');
$dist   = trim($_POST['addr_district'] ?? '');
$subd   = trim($_POST['addr_subdistrict'] ?? '');
$zip    = preg_replace('/\D+/', '', $_POST['addr_postcode'] ?? '');
$addr   = trim($_POST['addr_line'] ?? '');

/* วาลิเดตพื้นฐาน */
if ($first === '' || $last === '') {
  echo json_encode(['ok' => false, 'error' => 'ชื่อ-นามสกุลไม่ครบ']);
  exit;
}
if (!preg_match('/^\d{9,10}$/', $phone)) {
  echo json_encode(['ok' => false, 'error' => 'เบอร์โทรไม่ถูกต้อง']);
  exit;
}

/* วาลิเดต DOB (ถ้ามีค่า) */
if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
  echo json_encode(['ok' => false, 'error' => 'รูปแบบวันเกิดไม่ถูกต้อง (YYYY-MM-DD)']);
  exit;
}

/* แมพ gender ถ้าฟอร์มมี prefer_not แต่ ENUM ไม่มี -> เก็บเป็น NULL */
if ($gender === 'prefer_not') $gender = null;

/* ให้ user_id เป็น PRIMARY KEY ใน user_profiles */
$sql = "
INSERT INTO user_profiles
  (user_id, first_name, last_name, phone, gender, dob,
   addr_line, addr_province, addr_district, addr_subdistrict, addr_postcode,
   created_at, updated_at)
VALUES
  (:uid, :first, :last, :phone, :gender, :dob,
   :addr, :prov, :dist, :subd, :zip,
   NOW(), NOW())
ON DUPLICATE KEY UPDATE
  first_name = VALUES(first_name),
  last_name  = VALUES(last_name),
  phone      = VALUES(phone),
  gender     = VALUES(gender),
  dob        = VALUES(dob),
  addr_line  = VALUES(addr_line),
  addr_province    = VALUES(addr_province),
  addr_district    = VALUES(addr_district),
  addr_subdistrict = VALUES(addr_subdistrict),
  addr_postcode    = VALUES(addr_postcode),
  updated_at = NOW()
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':uid'   => $userId,
  ':first' => $first,
  ':last'  => $last,
  ':phone' => $phone,
  ':gender' => $gender === 'prefer_not' ? null : $gender,
  ':dob'   => ($dob === '' ? null : $dob),
  ':addr'  => $addr,
  ':prov'  => ($prov === '' ? null : $prov),
  ':dist'  => ($dist === '' ? null : $dist),
  ':subd'  => ($subd === '' ? null : $subd),
  ':zip'   => ($zip  === '' ? null : $zip),
]);

echo json_encode(['ok' => true]);

$next = $_POST['next'] ?? '';
if ($next && preg_match('~^/~', $next)) {
  header('Location: ' . $next);
} else {
  header('Location: /page/profile.html?saved=1');
}
exit;
