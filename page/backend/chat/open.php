<?php
require __DIR__ . '/_bootstrap.php';
$me = require_login();

$itemId = isset($_GET['with_owner_of']) ? (int)$_GET['with_owner_of'] : 0;
if ($itemId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing with_owner_of']);
    exit;
}

try {
    $ownerShopId = null;

    // 1) ลองหาใน products ก่อน
    $stmt = $conn->prepare("SELECT shop_id FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $stmt->bind_result($ownerShopId);
    if (!$stmt->fetch()) {
        $ownerShopId = null;
    }
    $stmt->close();

    // 2) ถ้าไม่เจอ ลองหาใน exchange_items
    if (!$ownerShopId) {
        $stmt = $conn->prepare("SELECT shop_id FROM exchange_items WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->bind_result($ownerShopId);
        if (!$stmt->fetch()) {
            $ownerShopId = null;
        }
        $stmt->close();
    }

    if (!$ownerShopId) {
        echo json_encode(['ok' => false, 'error' => 'item not found']);
        exit;
    }

    // หา/สร้างห้อง (ผูกด้วย shop_id + user_id + item_id)
    $stmt = $conn->prepare("SELECT id FROM shop_chats WHERE shop_id=? AND user_id=? AND item_id=? LIMIT 1");
    $stmt->bind_param('iii', $ownerShopId, $me, $itemId);
    $stmt->execute();
    $stmt->bind_result($convId);
    if ($stmt->fetch()) {
        $stmt->close();
        echo json_encode(['ok' => true, 'conv_id' => (int)$convId]);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO shop_chats (shop_id,user_id,item_id) VALUES (?,?,?)");
    $stmt->bind_param('iii', $ownerShopId, $me, $itemId);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['ok' => true, 'conv_id' => (int)$newId]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
