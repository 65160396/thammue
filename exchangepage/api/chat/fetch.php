<?php
require __DIR__ . '/../_config.php';

$pdo = db();
$userId = me_id();
$convId = (int)($_GET['conv_id'] ?? 0);
$after  = (int)($_GET['after_id'] ?? 0);
if (!$userId || $convId<=0) json_err('BAD_REQ', 400);

// ตรวจสิทธิ์
$st = $pdo->prepare("SELECT 1 FROM conversations WHERE id=:c AND (user_a=:u OR user_b=:u)");
$st->execute([':c'=>$convId, ':u'=>$userId]);
if (!$st->fetch()) json_err('FORBIDDEN', 403);

$sql = "SELECT id, conv_id, sender_id, body,
        DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') AS created_at
        FROM messages
        WHERE conv_id=:c ".($after>0 ? "AND id > :a" : "")."
        ORDER BY id ASC
        LIMIT 100";
$st = $pdo->prepare($sql);
$p  = [':c'=>$convId]; if ($after>0) $p[':a']=$after;
$st->execute($p);
$rows = $st->fetchAll();

foreach ($rows as &$m) $m['is_mine'] = ((int)$m['sender_id'] === $userId);
echo json_encode($rows ?: [], JSON_UNESCAPED_UNICODE);
