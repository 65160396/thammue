<?php
// page/backend/me_profile.php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/config.php'; // เตรียม $conn = new mysqli(...)
$conn->set_charset('utf8mb4');

$userId = (int)$_SESSION['user_id'];

// ดึง users + โปรไฟล์
$sql = "
  SELECT
    u.id, u.name, u.email, u.username, u.avatar,
    p.first_name, p.last_name, p.gender, p.phone, p.dob,
    p.addr_line, p.addr_province, p.addr_district, p.addr_subdistrict, p.addr_postcode
  FROM users u
  LEFT JOIN user_profiles p ON p.user_id = u.id
  WHERE u.id = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

// คำนวณ display_name (name > username > email ก่อน @)
$display = trim($row['name'] ?? '');
if ($display === '' && !empty($row['username'])) $display = $row['username'];
if ($display === '' && !empty($row['email']))    $display = strstr($row['email'], '@', true);

echo json_encode([
    'ok' => true,
    'user' => [
        // ชุดเบา (เดิม)
        'id'           => (int)$row['id'],
        'name'         => $row['name'],
        'email'        => $row['email'],
        'username'     => $row['username'],
        'display_name' => $display,
        'avatar'       => $row['avatar'],
        // ชุดโปรไฟล์เต็ม (ใหม่)
        'first_name'       => $row['first_name'],
        'last_name'        => $row['last_name'],
        'gender'           => $row['gender'],
        'phone'            => $row['phone'],
        'dob'              => $row['dob'], // ควรเป็นรูป YYYY-MM-DD
        'addr_line'        => $row['addr_line'],
        'addr_province'    => $row['addr_province'],
        'addr_district'    => $row['addr_district'],
        'addr_subdistrict' => $row['addr_subdistrict'],
        'addr_postcode'    => $row['addr_postcode'],
    ],
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
