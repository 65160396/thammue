<?php
require __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$me = require_login();

$convId = (int)($_POST['conv_id'] ?? 0);
$body   = trim($_POST['body'] ?? '');
if ($convId <= 0 || $body === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

try {
    // 1) เอาข้อมูลห้อง + หา owner ของร้าน
    $stmt = $conn->prepare("SELECT shop_id, user_id FROM shop_chats WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $stmt->bind_result($shopId, $buyerUserId);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }
    $stmt->close();

    // owner ของร้าน (user id)
    $stmt = $conn->prepare("SELECT user_id FROM shops WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $shopId);
    $stmt->execute();
    $stmt->bind_result($ownerUserId);
    $stmt->fetch();
    $stmt->close();

    if (!$ownerUserId) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'shop_owner_not_found']);
        exit;
    }

    // 2) ตรวจสิทธิ์ + ระบุว่าเราส่งในนามใคร
    $sender_shop_id = null;
    $sender_user_id = null;

    if ($me === (int)$buyerUserId) {
        // ผู้ซื้อส่ง
        $sender_user_id = $me;
    } elseif ($me === (int)$ownerUserId) {
        // ร้าน (เจ้าของ) ส่ง
        $sender_shop_id = $shopId;
    } else {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    // 3) กันสแปม: จำกัดความยาว
    if (mb_strlen($body) > 3000) {
        $body = mb_substr($body, 0, 3000);
    }

    // 4) บันทึกข้อความ
    $stmt = $conn->prepare(
        "INSERT INTO shop_chat_messages (conv_id, sender_shop_id, sender_user_id, body)
         VALUES (?,?,?,?)"
    );
    $stmt->bind_param('iiis', $convId, $sender_shop_id, $sender_user_id, $body);
    $stmt->execute();
    $stmt->close();

    // 5) อัปเดตเวลาแก้ไขห้อง
    $stmt = $conn->prepare("UPDATE shop_chats SET updated_at=NOW() WHERE id=?");
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $stmt->close();

    // 6) ทำแจ้งเตือนให้ "อีกฝ่าย"
    $notify_user_id = null;
    if ($sender_user_id) {
        // ผู้ซื้อส่ง -> แจ้งเจ้าของร้าน
        $notify_user_id = (int)$ownerUserId;
    } else {
        // ร้านส่ง -> แจ้งผู้ซื้อ
        $notify_user_id = (int)$buyerUserId;
    }

    if ($notify_user_id && $notify_user_id !== $me) {
        $title = 'มีข้อความใหม่จากแชท';
        $url   = '/page/storepage/chat.html?c=' . $convId;

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, url) VALUES (?,?,?)");
        $stmt->bind_param('iss', $notify_user_id, $title, $url);
        $stmt->execute();
        $stmt->close();
    }

    // 7) ตอบกลับครั้งเดียว
    echo json_encode(['ok' => true]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
