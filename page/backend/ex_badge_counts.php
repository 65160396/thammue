<?php
// /page/backend/ex_badge_counts.php
require_once __DIR__ . '/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
    $m = dbx();
    $uid = me();
    if (!$uid) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'not_logged_in'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* 1) คำขอแลกของที่ “เป็นของเราเอง” ที่ยัง pending */
    $st = $m->prepare("
      SELECT COUNT(*)
      FROM ex_requests r
      JOIN ex_items it_req ON it_req.id = r.requested_item_id
      WHERE it_req.user_id=? AND r.status='pending'
    ");
    $st->bind_param("i", $uid);
    $st->execute();
    $pending = (int)$st->get_result()->fetch_row()[0];

    /* 2) รายการโปรดของฉัน */
    $st2 = $m->prepare("SELECT COUNT(*) FROM ex_favorites WHERE user_id=?");
    $st2->bind_param("i", $uid);
    $st2->execute();
    $favCount = (int)$st2->get_result()->fetch_row()[0];

    /* 3) แชทยังไม่ได้อ่าน (ถ้า schema รองรับ) */
    $chatUnread = 0;
    $chk = $m->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ex_chat_messages' AND COLUMN_NAME IN ('recipient_id','is_read')");
    $hasCols = (int)$chk->fetch_row()[0];
    if ($hasCols >= 2) {
        $st3 = $m->prepare("SELECT COUNT(*) FROM ex_chat_messages WHERE recipient_id=? AND is_read=0");
        $st3->bind_param("i", $uid);
        $st3->execute();
        $chatUnread = (int)$st3->get_result()->fetch_row()[0];
    }

    /* 4) notifications ที่ยังไม่ได้อ่าน */
    $m->query("CREATE TABLE IF NOT EXISTS ex_notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL, type VARCHAR(50) NOT NULL, ref_id INT NULL,
      title VARCHAR(255) NOT NULL, body TEXT NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY(user_id), KEY(type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $st4 = $m->prepare("SELECT COUNT(*) FROM ex_notifications WHERE user_id=? AND is_read=0");
    $st4->bind_param("i", $uid);
    $st4->execute();
    $unreadNoti = (int)$st4->get_result()->fetch_row()[0];

    // ส่งออกทั้งคีย์เก่า (เพื่อความเข้ากันได้) และคีย์ใหม่ที่ header ใช้จริง
    echo json_encode([
        'ok'                     => true,
        // เดิม
        'pending_requests'       => $pending,
        'unread_notifications'   => $unreadNoti,
        // ใหม่
        'incoming_requests'      => $pending,
        'favorites'              => $favCount,
        'unread_messages'        => $chatUnread,
        'notifications_unread'   => $unreadNoti,   // <-- คีย์ที่ header/new JS ใช้
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'fatal', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
