<?php
// ยกเลิกรายการที่ "ฉันเป็นผู้ขอ" และยังเป็นสถานะ pending
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php'; // <-- ใช้ไฟล์นี้เท่านั้น (เชื่อม shopdb_ex)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  // รับได้ทั้ง JSON และ form
  $in = $_POST;
  if (empty($in)) {
    $raw = file_get_contents('php://input');
    if ($raw) $in = json_decode($raw, true) ?: [];
  }
  $rid = (int)($in['request_id'] ?? 0);
  if ($rid <= 0) jerr('bad_request');

  // ดึงคำขอ
  $st = $mysqli->prepare("SELECT id, requester_user_id, owner_user_id, status
                          FROM ex_requests WHERE id=?");
  $st->bind_param('i', $rid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  if (!$r) jerr('not_found', 404);

  // ต้องเป็นผู้ขอ และยัง pending
  if ((int)$r['requester_user_id'] !== (int)$uid) jerr('forbidden', 403);
  if ($r['status'] !== 'pending') jerr('invalid_status', 400);

  // อัปเดตเป็น cancelled
  $st = $mysqli->prepare("UPDATE ex_requests
                          SET status='cancelled', updated_at=NOW()
                          WHERE id=?");
  $st->bind_param('i', $rid);
  if (!$st->execute()) jerr('db_update_fail', 500);

  // แจ้งเตือนเจ้าของสินค้า
  $owner_id = (int)$r['owner_user_id'];
  $typ   = 'status';
  $title = 'ผู้ขอแลกยกเลิกรายการ';
  $body  = 'คำขอแลกเปลี่ยนถูกยกเลิกโดยผู้ขอ';
  $st = $mysqli->prepare("INSERT INTO ex_notifications
          (user_id, type, ref_id, title, body, is_read, created_at)
          VALUES (?,?,?,?,?,0,NOW())");
  $st->bind_param('isiss', $owner_id, $typ, $rid, $title, $body);
  $st->execute(); // ไม่ต้อง hard fail ถ้า insert noti ล้มเหลว

  jok(); // { ok: true }
} catch (Throwable $e) {
  // กันกรณี error หลุด
  jerr('server_error', 500);
}
