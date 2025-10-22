<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$rid = (int)($input['request_id'] ?? 0);
if ($rid<=0) jerr('bad_request');

$st = $mysqli->prepare("SELECT * FROM ex_requests WHERE id=?");
$st->bind_param("i", $rid);
$st->execute();
$r = $st->get_result()->fetch_assoc();

if (!$r) jerr('not_found', 404);
if ((int)$r['owner_user_id'] !== $uid) jerr('forbidden', 403);
if ($r['status'] !== 'pending') jerr('invalid_status', 400);

$st = $mysqli->prepare("UPDATE ex_requests SET status='declined', updated_at=NOW() WHERE id=?");
$st->bind_param("i", $rid);
$st->execute();

$user_id = (int)$r['requester_user_id'];
$typ='status'; $title='คำขอถูกปฏิเสธ'; $body='คำขอแลกเปลี่ยนของคุณถูกปฏิเสธ';
$st = $mysqli->prepare("INSERT INTO ex_notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?,?,?,?,?,0,NOW())");
$st->bind_param("isiss", $user_id, $typ, $rid, $title, $body);
$st->execute();

jok();
