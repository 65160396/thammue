<?php
require __DIR__ . '/../_config_admin.php';
require_admin();
$project = current_project();           // 'shop' หรือ 'exchange'
$pdo = db_for($project);

$limit = max(10, (int)($_GET['limit'] ?? 20));
$st = $pdo->query("
  SELECT t.id,
    (SELECT body FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
    (SELECT created_at FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) AS last_at
  FROM chat_threads t
  ORDER BY last_at DESC NULLS LAST
  LIMIT $limit
");
json_ok(['rows'=>$st->fetchAll()]);
