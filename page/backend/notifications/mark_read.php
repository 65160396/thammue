<?php
// /page/backend/notifications/mark_read.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../_guard.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$ids = isset($payload['ids']) && is_array($payload['ids']) ? $payload['ids'] : [];

try {
    if (empty($ids)) {
        // อ่านแล้วทั้งหมดของ user ปัจจุบัน
        $stmt = $conn->prepare("UPDATE app_notifications SET is_read=1 WHERE user_id=? AND is_read=0");
        $stmt->bind_param('i', $CURRENT_UID);
        $stmt->execute();
    } else {
        // อ่านเฉพาะ id ที่ส่งมา (จำกัดสิทธิ์ด้วย user_id)
        // สร้าง placeholder (?, ?, ?, ...)
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE app_notifications SET is_read=1 WHERE user_id=? AND id IN ($in)";

        // ประกอบ bind types
        $types = 'i' . str_repeat('i', count($ids));
        $params = array_merge([$types, $CURRENT_UID], array_map('intval', $ids));

        // mysqli bind_param ต้องใช้ call_user_func_array
        $stmt = $conn->prepare($sql);
        $tmp = [];
        foreach ($params as $key => $value) {
            $tmp[$key] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);

        $stmt->execute();
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
