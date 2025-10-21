<?php
require __DIR__ . '/../_config.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$userId = me_id();
$convId = (int)($_POST['conv_id'] ?? 0);
$body   = trim((string)($_POST['body'] ?? ''));
if (!$userId || $convId<=0 || $body==='') json_err('BAD_REQ', 400);

// ตรวจสิทธิ์
$st = $pdo->prepare("SELECT 1 FROM conversations WHERE id=:c AND (user_a=:u OR user_b=:u)");
$st->execute([':c'=>$convId, ':u'=>$userId]);
if (!$st->fetch()) json_err('FORBIDDEN', 403);

$pdo->beginTransaction();
try {
  $pdo->prepare("INSERT INTO messages (conv_id, sender_id, body, created_at) VALUES (:c,:u,:b,NOW())")
      ->execute([':c'=>$convId, ':u'=>$userId, ':b'=>$body]);
  $pdo->prepare("UPDATE conversations SET last_msg_at=NOW() WHERE id=:c")->execute([':c'=>$convId]);
  $pdo->commit();
  json_ok();
} catch (Throwable $e) {
  $pdo->rollBack(); json_err('EXCEPTION', 500, ['message'=>$e->getMessage()]);
}
$pdo->prepare("UPDATE conversations SET last_msg_at = NOW() WHERE id = :c")
    ->execute([':c' => $convId]);
