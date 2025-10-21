<?php
require __DIR__ . '/../_config_admin.php';
$aid = require_admin(); verify_csrf();
$project = current_project(); // 'exchange'
$pdo = db_for($project);

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id  = (int)($in['id'] ?? 0);
$act = $in['action'] ?? '';
$note= trim($in['note'] ?? '');
if (!$id || !in_array($act,['approve','reject'],true)) json_err('bad_req',400);

$st = $pdo->prepare("UPDATE kyc_submissions SET status=:s, note=:n, decided_at=NOW(), decided_by=:a WHERE id=:id AND status='pending'");
$st->execute([
  ':s'=>$act==='approve'?'approved':'rejected',
  ':n'=>$note, ':a'=>$aid, ':id'=>$id
]);
json_ok(['ok'=>$st->rowCount()>0]);
