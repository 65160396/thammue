<?php
// สำหรับโชว์ badge มุมขวา: จำนวนคำขอ pending (ขาเข้า) + จำนวนแจ้งเตือนที่ยังไม่อ่าน
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// pending requests (incoming)
$st = $mysqli->prepare("SELECT COUNT(*) FROM ex_requests WHERE owner_user_id=? AND status='pending'");
$st->bind_param("i", $uid);
$st->execute();
$pending = (int)$st->get_result()->fetch_row()[0];

// notifications unread
$st2 = $mysqli->prepare("SELECT COUNT(*) FROM ex_notifications WHERE user_id=? AND is_read=0");
$st2->bind_param("i", $uid);
$st2->execute();
$unread = (int)$st2->get_result()->fetch_row()[0];

jok(['pending_requests'=>$pending,'unread_notifications'=>$unread]);
