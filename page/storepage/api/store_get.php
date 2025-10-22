<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require __DIR__ . '/../../backend/config.php';  // ไฟล์ของคุณให้ $conn (mysqli)

$shopId = (int)($_GET['shop_id'] ?? $_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($shopId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

try {
    // ตรวจและดึงข้อมูลร้านของผู้ใช้คนนั้น
    $stmt = $conn->prepare(
        "SELECT id, user_id, shop_name, pickup_addr, email, phone, status
     FROM shops WHERE id=? AND user_id=? LIMIT 1"
    );
    $stmt->bind_param("ii", $shopId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'not_found_or_no_permission']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
