<?php
require __DIR__ . '/../_config_admin.php';
require_admin();
$project = current_project(); // 'exchange'
$pdo = db_for($project);

$status = $_GET['status'] ?? 'open';
$st = $pdo->prepare("SELECT * FROM reports WHERE status=:s ORDER BY created_at DESC");
$st->execute([':s'=>$status]);
json_ok(['rows'=>$st->fetchAll()]);
