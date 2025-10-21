<?php
require __DIR__ . '/../_config_admin.php';
$aid = require_admin(); verify_csrf();
$project = current_project();
$pdo = db_for($project);

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$tid = (int)($in['thread_id'] ?? 0);
$body= trim($in['body'] ?? '');
if(!$tid || $body==='') json_err('bad_req');

$st = $pdo->prepare("INSERT INTO chat_messages (thread_id,sender_id,body) VALUES (:t,:s,:b)");
$st->execute([':t'=>$tid, ':s'=>$aid, ':b'=>$body]);
json_ok(['id'=>$pdo->lastInsertId()]);
