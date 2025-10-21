<?php
require __DIR__ . '/../_config_admin.php';
require_admin();
$project = current_project();
$pdo = db_for($project);

$tid = (int)($_GET['thread_id'] ?? 0);
if(!$tid) json_err('bad_req');

$st = $pdo->prepare("SELECT id, sender_id, body, created_at FROM chat_messages WHERE thread_id=:t ORDER BY id ASC");
$st->execute([':t'=>$tid]);
json_ok(['rows'=>$st->fetchAll()]);
