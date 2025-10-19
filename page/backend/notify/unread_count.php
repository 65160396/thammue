<?php
require __DIR__ . '/../chat/_bootstrap.php'; // ใช้ session + $conn เดิม
$me = require_login();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stmt->bind_param('i', $me);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

echo json_encode(['ok' => true, 'count' => (int)$count]);
