<?php
// /page/backend/ex_request_decide.php
require_once __DIR__ . '/ex__common.php';

header('Content-Type: application/json; charset=utf-8');

try {
  // ให้ mysqli โยน exception เวลา error เพื่อจะได้ try/catch ส่ง JSON
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  $m = dbx();
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $uid = me();
  if (!$uid) jerr('not_logged_in', 401);

  $rid    = (int)($_POST['request_id'] ?? 0);
  $action = trim((string)($_POST['action'] ?? '')); // accept | decline | cancel
  if ($rid <= 0) jerr('bad_request');

  // ดึงคำขอ
  $st = $m->prepare("SELECT * FROM ex_requests WHERE id=? LIMIT 1");
  $st->bind_param("i", $rid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  if (!$r) jerr('not_found', 404);
  if ($r['status'] !== 'pending') jerr('invalid_status', 400);

  $requester = (int)$r['requester_user_id']; // คนขอ
  $owner     = (int)$r['owner_user_id'];     // เจ้าของของที่ถูกขอ

  // ---------- ผู้ขอยกเลิก ----------
// ----- เจ้าของยอมรับ -----
if ($action === 'accept') {
  if ($uid !== $owner) jerr('forbidden', 403);

  $meet_date = trim((string)($_POST['meet_date'] ?? '')); // YYYY-MM-DD
  $meet_time = trim((string)($_POST['meet_time'] ?? '')); // HH:MM
  $place     = trim((string)($_POST['place'] ?? ''));
  $note      = trim((string)($_POST['note'] ?? ''));

  $m->begin_transaction();
  try {
    // อัปเดตสถานะ ex_requests
    $st = $m->prepare("UPDATE ex_requests SET status='accepted', updated_at=NOW() WHERE id=?");
    $st->bind_param("i", $rid);
    $st->execute();

    // บันทึก/อัปเดตนัดหมายลง ex_meetings (upsert)
    $st = $m->prepare("
      INSERT INTO ex_meetings
        (request_id, owner_user_id, requester_user_id, meet_date, meet_time, meet_place, meet_note, created_at, updated_at)
      VALUES
        (?,?,?,?,?,?,?, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        meet_date=VALUES(meet_date),
        meet_time=VALUES(meet_time),
        meet_place=VALUES(meet_place),
        meet_note=VALUES(meet_note),
        updated_at=NOW()
    ");
    $st->bind_param("iiissss", $rid, $owner, $requester, $meet_date, $meet_time, $place, $note);
    $st->execute();

    // แจ้งเตือนผู้ขอ พร้อมรายละเอียดสั้น ๆ
    $title = 'ผู้ขายยอมรับคำขอของคุณ';
    $dt    = trim(($meet_date ?: '') . ' ' . ($meet_time ?: ''));
    $detail= [];
    if ($dt)    $detail[] = "วันเวลา: $dt";
    if ($place) $detail[] = "สถานที่: $place";
    if ($note)  $detail[] = "หมายเหตุ: $note";
    $body  = $detail ? implode(' | ', $detail) : 'โปรดเปิดหน้าตารางนัดหมายเพื่อดูรายละเอียด';
    $typ   = 'status';
    $st = $m->prepare("INSERT INTO ex_notifications (user_id,type,ref_id,title,body,is_read,created_at)
                       VALUES (?,?,?,?,?,0,NOW())");
    $st->bind_param("isiss", $requester, $typ, $rid, $title, $body);
    $st->execute();

    $m->commit();
    jok(['request_id'=>$rid]);
  } catch (Throwable $e) {
    $m->rollback();
    jerr('sql_fail: '.$e->getMessage(), 500);
  }
}



  jerr('bad_action', 400);

} catch (Throwable $e) {
  jerr('fatal: '.$e->getMessage(), 500);
}
