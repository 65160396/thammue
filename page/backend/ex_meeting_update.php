<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$rid = (int)($input['request_id'] ?? 0);
$scheduled_at = trim((string)($input['scheduled_at'] ?? '')); // 'YYYY-MM-DD HH:MM:SS'
$place = trim((string)($input['place'] ?? ''));
$note  = trim((string)($input['note'] ?? ''));
if ($rid<=0) jerr('bad_request');

$st = $mysqli->prepare("SELECT requester_user_id, owner_user_id FROM ex_requests WHERE id=?");
$st->bind_param("i", $rid);
$st->execute();
$r = $st->get_result()->fetch_assoc();
if (!$r) jerr('not_found', 404);
if ($uid !== (int)$r['requester_user_id'] && $uid !== (int)$r['owner_user_id']) jerr('forbidden', 403);

$st = $mysqli->prepare("
  INSERT INTO ex_meetings (request_id, scheduled_at, place, note, created_at, updated_at)
  VALUES (?,?,?,?,NOW(),NOW())
  ON DUPLICATE KEY UPDATE scheduled_at=VALUES(scheduled_at), place=VALUES(place), note=VALUES(note), updated_at=NOW()
");
$sa = ($scheduled_at !== '') ? $scheduled_at : None;
# mysqli doesn't allow python None; will convert below
$sa = ($scheduled_at !== '') ? $scheduled_at : NULL;
$st->bind_param("isss", $rid, $sa, $place, $note);
$st->execute();

$other = ($uid===(int)$r['requester_user_id'])? (int)$r['owner_user_id'] : (int)$r['requester_user_id'];
$typ='meeting'; $title='มีการอัปเดตนัดหมาย'; $body='มีการอัปเดตเวลา/สถานที่นัดหมายเพื่อแลกเปลี่ยน';
$st = $mysqli->prepare("INSERT INTO ex_notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?,?,?,?,?,0,NOW())");
$st->bind_param("isiss", $other, $typ, $rid, $title, $body);
$st->execute();
jok();
