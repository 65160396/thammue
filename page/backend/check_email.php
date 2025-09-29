<?php
// /page/backend/check_email.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php'; // มี $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'reason' => 'invalid_email']);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
$taken = $stmt->num_rows > 0;
$stmt->close();

echo json_encode(['ok' => true, 'taken' => $taken]);
