<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$type = $_GET['type'] ?? 'product';
if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';

/* ---------- โหมดสรุป: นับจำนวนรายการโปรดทั้งหมดของผู้ใช้ ---------- */
if (isset($_GET['summary']) && $_GET['summary'] === 'favorites') {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        echo json_encode(['total_favorites' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // ถ้าต้องการนับเฉพาะสินค้าปกติ
    $st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND item_type=?");
    $st->execute([$uid, $type]);
    echo json_encode(['total_favorites' => (int)$st->fetchColumn()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- โหมดเดิม: สถานะ like ของ item ---------- */
$id   = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad id']);
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type, $id]);
$likeCount = (int)$st->fetchColumn();

$likedByMe = false;
if ($userId > 0) {
    $st = $pdo->prepare("SELECT 1 FROM favorites WHERE item_type=? AND item_id=? AND user_id=?");
    $st->execute([$type, $id, $userId]);
    $likedByMe = (bool)$st->fetchColumn();
}

// (ถ้ามีต้องการ sold_count ก็ใส่เพิ่มเหมือนเดิม)
echo json_encode([
    'count'       => $likeCount,
    'liked'       => $likedByMe,
    // 'sold_count' => ...
], JSON_UNESCAPED_UNICODE);

if (isset($_GET['summary']) && $_GET['summary'] === 'favorites') {
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    $type = $_GET['type'] ?? 'product';
    if (!in_array($type, ['product', 'exchange'], true)) $type = 'product';

    if ($uid <= 0) {
        echo json_encode(['total_favorites' => 0]);
        exit;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND item_type=?");
    $st->execute([$uid, $type]);
    echo json_encode(['total_favorites' => (int)$st->fetchColumn()], JSON_UNESCAPED_UNICODE);
    exit;
}
