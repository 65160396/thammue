<?php
require __DIR__ . '/../_config_admin.php';
$aid = require_admin(); verify_csrf();
$project = current_project(); // 'shop'
$pdo = db_for($project);

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($in['id'] ?? 0);
if(!$id) json_err('bad_req');

$st = $pdo->prepare("UPDATE stores SET status='approved', decided_at=NOW(), decided_by=:a WHERE id=:id AND status='pending'");
$st->execute([':a'=>$aid, ':id'=>$id]);
json_ok(['ok'=>$st->rowCount()>0]);

