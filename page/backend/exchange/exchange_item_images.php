<?php
// /page/backend/exchange/exchange_item_images.php
session_start();
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $item_id = (int)($_GET['item_id'] ?? 0);
    if ($item_id <= 0) {
        http_response_code(400);
        exit('bad item_id');
    }
    $q = $conn->prepare("SELECT id,file_path,sort_order FROM exchange_item_images WHERE item_id=? ORDER BY sort_order,id");
    $q->bind_param('i', $item_id);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'images' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    // ตรวจเจ้าของในงานจริง (ข้ามในโหมด dev)
    $img_id = (int)($_POST['img_id'] ?? 0);
    if ($img_id <= 0) {
        http_response_code(400);
        exit('bad img_id');
    }

    // หา path เพื่อลบไฟล์จริง
    $q = $conn->prepare("SELECT file_path FROM exchange_item_images WHERE id=? LIMIT 1");
    $q->bind_param('i', $img_id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();

    if ($row) {
        $path = $_SERVER['DOCUMENT_ROOT'] . $row['file_path'];
        if (is_file($path)) @unlink($path);
        $conn->query("DELETE FROM exchange_item_images WHERE id={$img_id} LIMIT 1");
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo 'Method Not Allowed';
