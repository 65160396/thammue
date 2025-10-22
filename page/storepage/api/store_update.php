<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require __DIR__ . '/../../_config.php';

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
    $pdo = db();
    $chk = $pdo->prepare("SELECT id FROM shops WHERE id=? AND user_id=? LIMIT 1");
    $chk->execute([$shopId, $userId]);
    if (!$chk->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'not_found_or_no_permission']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE shops SET shop_name=:shop_name,email=:email,phone=:phone,pickup_addr=:pickup_addr,updated_at=NOW() WHERE id=:id");
    $stmt->execute([':shop_name' => $shop_name, ':email' => $email, ':phone' => $phone, ':pickup_addr' => $pickup_addr, ':id' => $shopId]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
