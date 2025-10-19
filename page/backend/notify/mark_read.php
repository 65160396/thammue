<?php
require __DIR__ . '/../chat/_bootstrap.php';
$me = require_login();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false]); exit; }

$stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
$stmt->bind_param('ii', $id, $me);
$stmt->execute(); $stmt->close();

echo json_encode(['ok'=>true]);
