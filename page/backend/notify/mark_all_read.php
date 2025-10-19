<?php
require __DIR__ . '/../chat/_bootstrap.php';
$me = require_login();
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=" . (int)$me);
echo json_encode(['ok' => true]);
