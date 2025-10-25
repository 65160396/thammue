<?php
require_once __DIR__ . '/ex__common.php';
$m = dbx();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$uid = me();
if (!$uid) jerr('not_logged_in',401);

$room_id = (int)($_POST['room_id'] ?? ($_POST['chat_id'] ?? 0));
$body    = trim((string)($_POST['body'] ?? ''));
if ($room_id<=0 || $body==='') jerr('bad_params');

// ต้องเป็นสมาชิกห้อง
$st = $m->prepare("SELECT 1 FROM ex_chat_participants WHERE room_id=? AND user_id=?");
$st->bind_param("ii",$room_id,$uid);
$st->execute();
if (!$st->get_result()->fetch_row()) jerr('forbidden',403);

// บันทึกข้อความ
$st = $m->prepare("INSERT INTO ex_chat_messages (room_id,user_id,body,created_at)
                   VALUES (?,?,?,NOW())");
$st->bind_param("iis",$room_id,$uid,$body);
$st->execute();

// อัปเดตเวลา
$m->query("UPDATE ex_chat_rooms SET updated_at=NOW() WHERE id=".(int)$room_id);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
