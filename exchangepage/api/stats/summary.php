<?php
require_once __DIR__ . '/../_config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }

/* ตัวอย่าง query:
   - total_pending: คำขอแลกเปลี่ยนที่ยังค้างสำหรับ "เจ้าของของ" คนนี้
   - total_unread: ข้อความยังไม่ได้อ่านในห้องที่ user เข้าร่วม
   - total_favorites: มีไว้เผื่อคุณอยากใช้อีกปุ่ม (ไม่จำเป็นก็ได้)
*/

/* favorites (ทางเลือก) */
$stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
$stmt->execute([$userId]);
$fav = (int)$stmt->fetchColumn();

/* pending requests */
$stmt = $pdo->prepare('
  SELECT COUNT(*)
  FROM exchange_requests r
  JOIN items i ON r.item_id = i.id
  WHERE i.owner_id = ? AND r.status = \'pending\'
');
$stmt->execute([$userId]);
$pending = (int)$stmt->fetchColumn();

/* chat unread */
$stmt = $pdo->prepare('
  SELECT COALESCE(SUM(unread_cnt),0) AS total
  FROM (
    SELECT COUNT(m.id) AS unread_cnt
    FROM chat_rooms r
    JOIN chat_participants p ON p.room_id = r.id AND p.user_id = ?
    JOIN chat_messages m ON m.room_id = r.id
    LEFT JOIN chat_reads cr ON cr.room_id = r.id AND cr.user_id = ?
    WHERE m.sender_id <> ?
      AND (cr.last_read_at IS NULL OR m.created_at > cr.last_read_at)
    GROUP BY r.id
  ) x
');
$stmt->execute([$userId, $userId, $userId]);
$unread = (int)$stmt->fetchColumn();

echo json_encode([
  'ok'              => true,
  'total_favorites' => $fav,      // เผื่อใช้ภายหลัง
  'total_pending'   => $pending,  // ใช้กับ reqBadge
  'total_unread'    => $unread    // ใช้กับ chatBadge
]);
