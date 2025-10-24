<?php
// /page/backend/ex_request_decide.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$request_id = (int)($_POST['request_id'] ?? 0);
$action     = strtolower(trim((string)($_POST['action'] ?? '')));
$meet_at    = trim((string)($_POST['meet_at'] ?? ''));   // '2025-10-22 14:00' (ออปชัน)
$meet_note  = trim((string)($_POST['meet_note'] ?? '')); // ออปชัน

if ($request_id<=0 || !in_array($action, ['accept','decline'], true)) jerr('bad_params',400);

// โหลดคำขอ
$st = $mysqli->prepare("SELECT * FROM ex_requests WHERE id=?");
$st->bind_param("i", $request_id);
$st->execute();
$req = $st->get_result()->fetch_assoc();
if (!$req) jerr('not_found',404);
if ((int)$req['owner_user_id'] !== $uid) jerr('forbidden',403);
if ($req['status']!=='pending') jerr('invalid_status',400);

// อัปเดตสถานะ
$new = ($action==='accept') ? 'accepted' : 'declined';
$st = $mysqli->prepare("UPDATE ex_requests SET status=?, updated_at=NOW() WHERE id=?");
$st->bind_param("si", $new, $request_id);
$st->execute();

// แจ้งเตือนไปฝั่งผู้ขอ
$to_user = (int)$req['requester_user_id'];
if ($action==='accept'){
  $title = 'ผู้แลกยอมรับคำขอของคุณแล้ว';
  $body  = 'โปรดไปที่แชทเพื่อสนทนารายละเอียด'.($meet_at?(" | นัดรับ: ".$meet_at):'').($meet_note?(" | หมายเหตุ: ".$meet_note):'');
  $typ   = 'request_accepted';
} else {
  $title = 'คำขอแลกของคุณถูกปฏิเสธ';
  $body  = 'เจ้าของสินค้าปฏิเสธคำขอแลกเปลี่ยน';
  $typ   = 'request_declined';
}
$st = $mysqli->prepare("INSERT INTO ex_notifications (user_id, type, ref_id, title, body, is_read, created_at)
                        VALUES (?, ?, ?, ?, ?, 0, NOW())");
$st->bind_param("isiss", $to_user, $typ, $request_id, $title, $body);
$st->execute();

jok(['status'=>$new]);
