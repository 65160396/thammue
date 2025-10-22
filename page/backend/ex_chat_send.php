<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$room_id = (int)($input['room_id'] ?? 0);
$body = trim((string)($input['body'] ?? ''));
if ($room_id<=0 || $body==='') jerr('bad_request');

$chk = $mysqli->prepare("SELECT 1 FROM ex_chat_participants WHERE user_id=? AND room_id=?");
$chk->bind_param("ii", $uid, $room_id);
$chk->execute();
$ok = $chk->get_result()->fetch_row();
if (!$ok) jerr('forbidden', 403);

$stmt = $mysqli->prepare("INSERT INTO ex_chat_messages (room_id, user_id, body, created_at) VALUES (?,?,?,NOW())");
$stmt->bind_param("iis", $room_id, $uid, $body);
$stmt->execute();

// bump room updated_at
$stmt = $mysqli->prepare("UPDATE ex_chat_rooms SET updated_at=NOW() WHERE id=?");
$stmt->bind_param("i", $room_id);
$stmt->execute();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
