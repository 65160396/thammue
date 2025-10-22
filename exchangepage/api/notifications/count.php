<?php
// /exchangepage/api/notifications/count.php
require_once __DIR__ . '/_guard.php';
$pdo = db();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([me()]);
echo json_encode(['unread' => (int)$stmt->fetchColumn()], JSON_UNESCAPED_UNICODE);
