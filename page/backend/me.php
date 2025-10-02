<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';   // ให้ไฟล์นี้มี $conn = new mysqli(...);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok' => false]);
  exit;
}

$uid = (int) $_SESSION['user_id'];

// อ่านจาก users + user_profiles
$sql = "
SELECT 
  u.id, u.email, u.name, 
  p.first_name, p.last_name, p.phone, p.gender, p.dob,
  p.addr_line, p.addr_province, p.addr_district, p.addr_subdistrict, p.addr_postcode
FROM users u
LEFT JOIN user_profiles p ON p.user_id = u.id
WHERE u.id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$res  = $stmt->get_result();
$row  = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  echo json_encode(['ok' => false]);
  exit;
}

// ✅ ประกอบ display_name เพื่อให้ JS เดิมใช้ได้ทันที
$display = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
if ($display === '') {
  // ถ้าไม่มีชื่อจริง-นามสกุล ให้ใช้ชื่อเดิมที่สมัคร (users.name)
  $display = $row['name'] ?? '';
}

// รวมข้อมูลที่จะส่งออก
$user = [
  'id'              => (int)$row['id'],
  'email'           => $row['email'],
  'name'            => $row['name'],          // ชื่อที่สมัคร (username/display)
  'display_name'    => $display,              // ✅ ใช้แสดงในเมนู (JS เดิมอ่านตัวนี้)
  'first_name'      => $row['first_name'],
  'last_name'       => $row['last_name'],
  'phone'           => $row['phone'],
  'gender'          => $row['gender'],
  'dob'             => $row['dob'],
  'addr_line'       => $row['addr_line'],
  'addr_province'   => $row['addr_province'],
  'addr_district'   => $row['addr_district'],
  'addr_subdistrict' => $row['addr_subdistrict'],
  'addr_postcode'   => $row['addr_postcode'],
];

// ส่งกลับในรูปแบบเดิม (มี ok และ user)
echo json_encode(['ok' => true, 'user' => $user]);
