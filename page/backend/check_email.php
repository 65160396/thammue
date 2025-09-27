<?php
// page/backend/check_email.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => true, 'exists' => false, 'reason' => 'invalid']);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'exists' => $exists]);
