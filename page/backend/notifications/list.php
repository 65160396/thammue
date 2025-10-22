<?php
// /page/backend/notifications/list.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../_guard.php';

$limit  = isset($_GET['limit'])  ? max(1, min(50, (int)$_GET['limit'])) : 20;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

try {
    $stmt = $conn->prepare(
        "SELECT id, type, title, body, is_read, created_at
     FROM app_notifications
     WHERE user_id=?
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('iii', $CURRENT_UID, $limit, $offset);
    $stmt->execute();
    $rs = $stmt->get_result();

    $items = [];
    while ($row = $rs->fetch_assoc()) {
        $items[] = [
            'id'         => (int)$row['id'],
            'type'       => $row['type'],
            'title'      => $row['title'],
            'body'       => $row['body'],
            'is_read'    => (bool)$row['is_read'],
            // ส่งเป็น "YYYY-MM-DD HH:MM:SS" ได้เลย โค้ดหน้าเว็บคุณรองรับ
            'created_at' => $row['created_at'],
        ];
    }
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['items' => []]);
}
