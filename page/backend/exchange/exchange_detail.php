<?php
// /page/backend/exchange/exchange_detail.php
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0){ http_response_code(400); exit('bad id'); }

$stmt = $conn->prepare("SELECT * FROM exchange_items WHERE id=? LIMIT 1");
$stmt->bind_param('i',$id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item){ http_response_code(404); exit('not found'); }

$imgs = [];
$st = $conn->prepare("SELECT id,file_path,sort_order FROM exchange_item_images WHERE item_id=? ORDER BY sort_order,id");
$st->bind_param('i',$id);
$st->execute();
$imgs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'item'=>$item,'images'=>$imgs], JSON_UNESCAPED_UNICODE);
