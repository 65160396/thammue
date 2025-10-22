<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require __DIR__ . '/../../backend/config.php';  // ใช้ $conn (mysqli)

$shopId = (int)($_POST['shop_id'] ?? $_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

$shop_name   = trim((string)($_POST['shop_name'] ?? ''));
$email       = trim((string)($_POST['email'] ?? ''));
$phone       = trim((string)($_POST['phone'] ?? ''));
$pickup_addr = trim((string)($_POST['pickup_addr'] ?? ''));

if ($shopId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}
if ($shop_name === '' || $email === '') {
    echo json_encode(['ok' => false, 'error' => 'กรุณากรอกชื่อร้านและอีเมล']);
    exit;
}

try {
    // ตรวจสิทธิ์เป็นเจ้าของร้าน
    $chk = $conn->prepare("SELECT id FROM shops WHERE id=? AND user_id=? LIMIT 1");
    $chk->bind_param("ii", $shopId, $userId);
    $chk->execute();
    $has = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$has) {
        echo json_encode(['ok' => false, 'error' => 'not_found_or_no_permission']);
        exit;
    }

    // อัปเดต
    $stmt = $conn->prepare(
        "UPDATE shops SET shop_name=?, email=?, phone=?, pickup_addr=?, updated_at=NOW()
     WHERE id=?"
    );
    $stmt->bind_param("ssssi", $shop_name, $email, $phone, $pickup_addr, $shopId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
