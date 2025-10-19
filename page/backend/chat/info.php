<?php
// /page/backend/chat/info.php
require __DIR__ . '/_bootstrap.php';
$me = require_login();

$convId = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;
if ($convId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing conv_id']);
    exit;
}

try {
    // 1) ดึงข้อมูลห้อง + ชื่อร้าน + ชื่อไอเท็ม
    $stmt = $conn->prepare(
        "SELECT c.shop_id, c.user_id, c.item_id,
            s.shop_name,
            COALESCE(i.title, p.name) AS item_title   -- ดึงจาก exchange_items หรือ products ก็ได้
     FROM shop_chats c
     LEFT JOIN shops s          ON s.id=c.shop_id
     LEFT JOIN exchange_items i ON i.id=c.item_id
     LEFT JOIN products p       ON p.id=c.item_id
     WHERE c.id=? LIMIT 1"
    );
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $stmt->bind_result($shopId, $userId, $itemId, $shopName, $itemTitle);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $stmt->close();

    // 2) ตรวจสิทธิ์: เป็นผู้ซื้อคนในห้อง หรือเป็นเจ้าของร้าน
    $isAllowed = ($me === (int)$userId);
    if (!$isAllowed) {
        $stmt = $conn->prepare("SELECT user_id FROM shops WHERE id=?");
        $stmt->bind_param('i', $shopId);
        $stmt->execute();
        $stmt->bind_result($ownerUid);
        $stmt->fetch();
        $stmt->close();
        $isAllowed = ($me === (int)$ownerUid);
    }
    if (!$isAllowed) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    // 3) ชื่ออีกฝ่าย: ถ้าเราเป็นผู้ซื้อ -> ชื่อร้าน, ถ้าเราเป็นร้าน -> ชื่อผู้ซื้อ
    $buyerName = null;
    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(name),''), CONCAT('ผู้ใช้ #', id)) FROM users WHERE id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($buyerName);
    $stmt->fetch();
    $stmt->close();

    $otherName = ($me === (int)$userId) ? ($shopName ?: 'ร้านค้า')
        : ($buyerName ?: 'ผู้ซื้อ');

    echo json_encode([
        'ok'         => true,
        'other_name' => $otherName,
        'item_title' => $itemTitle ?: null
    ]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
