<?php
// /page/backend/notifications/count.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../_guard.php';     // sets $CURRENT_UID, $conn (mysqli)

try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM app_notifications WHERE user_id=? AND is_read=0"
    );
    $stmt->bind_param('i', $CURRENT_UID);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $unread = (int)($res['c'] ?? 0);

    echo json_encode(['unread' => $unread], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['unread' => 0]);
}
