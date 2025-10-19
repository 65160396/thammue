<?php
require __DIR__ . '/../chat/_bootstrap.php'; // reuse session+config
$me = require_login();

$stmt = $conn->prepare("SELECT id, title, url, created_at
                        FROM notifications
                        WHERE user_id=? AND is_read=0
                        ORDER BY id DESC
                        LIMIT 10");
$stmt->bind_param('i', $me);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($r = $res->fetch_assoc()) $data[] = $r;
$stmt->close();

echo json_encode(['ok'=>true,'items'=>$data]);
