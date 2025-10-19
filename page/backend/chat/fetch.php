<?php
require __DIR__ . '/_bootstrap.php';
$me = require_login();

$convId  = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
if ($convId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT shop_id, user_id FROM shop_chats WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $stmt->bind_result($shopId, $userId);
    if (!$stmt->fetch()) {
        echo json_encode([]);
        exit;
    }
    $stmt->close();

    // สิทธิ์: ผู้ใช้ในห้อง หรือ เจ้าของร้าน
    $isOwner = false;
    if ($me !== (int)$userId) {
        $stmt = $conn->prepare("SELECT user_id FROM shops WHERE id=?");
        $stmt->bind_param('i', $shopId);
        $stmt->execute();
        $stmt->bind_result($ownerUid);
        $stmt->fetch();
        $stmt->close();
        $isOwner = ($me === (int)$ownerUid);
        if (!$isOwner) {
            http_response_code(403);
            echo json_encode([]);
            exit;
        }
    }

    $stmt = $conn->prepare(
        "SELECT id, conv_id, sender_shop_id, sender_user_id, body, created_at
     FROM shop_chat_messages
     WHERE conv_id=? AND id>?
     ORDER BY id ASC
     LIMIT 200"
    );
    $stmt->bind_param('ii', $convId, $afterId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($m = $res->fetch_assoc()) {
        $mine = ($m['sender_user_id'] && (int)$m['sender_user_id'] === $me)
            || ($m['sender_shop_id'] && $isOwner);
        $rows[] = [
            'id' => (int)$m['id'],
            'body' => $m['body'],
            'created_at' => $m['created_at'],
            'is_mine' => $mine
        ];
    }
    $stmt->close();

    echo json_encode($rows);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([]);
}
