<?php
// /page/backend/ex_messages_send.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in',401);

$chat_id = (int)($_POST['chat_id'] ?? 0);
$body    = trim((string)($_POST['body'] ?? ''));
if ($chat_id<=0 || $body==='') jerr('bad_params');

$st = $m->prepare("INSERT INTO ex_messages (chat_id, sender_id, body, created_at) VALUES (?, ?, ?, NOW())");
$st->bind_param("iis", $chat_id, $uid, $body);
$st->execute();

$m->query("UPDATE ex_chats SET last_message_at=NOW() WHERE id=".(int)$chat_id);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
