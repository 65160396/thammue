<?php
// /page/backend/ex_chat_send.php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$room_id = (int)($in['room_id'] ?? 0);
$body = trim((string)($in['body'] ?? ''));
if ($room_id<=0 || $body==='') jerr('bad_request', 400);

/* permission */
$st = $m->prepare("SELECT 1 FROM ".T_CHAT_PARTICIPANTS." WHERE room_id=? AND user_id=?");
$st->bind_param("ii", $room_id, $uid);
$st->execute();
if (!$st->get_result()->fetch_row()) jerr('forbidden', 403);

/* insert message */
$st = $m->prepare("INSERT INTO ".T_CHAT_MESSAGES." (room_id,user_id,body,created_at) VALUES (?,?,?,NOW())");
$st->bind_param("iis", $room_id, $uid, $body);
$st->execute();

/* bump room */
$u = $m->prepare("UPDATE ".T_CHAT_ROOMS." SET updated_at=NOW() WHERE id=?");
$u->bind_param("i", $room_id);
$u->execute();

jok(['message_id'=>(int)$m->insert_id]);
