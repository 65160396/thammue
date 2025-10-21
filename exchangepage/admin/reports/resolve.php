<?php
require __DIR__ . '/../_config_admin.php';
$aid = require_admin(); verify_csrf();
$project = current_project(); // 'exchange'
$pdo = db_for($project);

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($in['id'] ?? 0);
$to = $in['to'] ?? 'resolved'; // in_progress | resolved | invalid
$note = trim($in['note'] ?? '');
if(!$id || !in_array($to,['in_progress','resolved','invalid'],true)) json_err('bad_req');

$st = $pdo->prepare("UPDATE reports SET status=:s, admin_note=:n, updated_at=NOW() WHERE id=:id");
$st->execute([':s'=>$to, ':n'=>$note, ':id'=>$id]);
json_ok(['ok'=>true]);
