<?php
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx();
$uid = me();
if (!$uid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true) ?? [];
$ids = $raw['ids'] ?? [];

if (is_array($ids) && count($ids) > 0) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if ($ids) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids) + 1);
        $sql = "UPDATE ex_notifications SET is_read=1 WHERE user_id=? AND id IN ($place)";
        $st  = $m->prepare($sql);
        $st->bind_param($types, $uid, ...$ids);
        $st->execute();
    }
} else {
    $st = $m->prepare("UPDATE ex_notifications SET is_read=1 WHERE user_id=? AND is_read=0");
    $st->bind_param("i", $uid);
    $st->execute();
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
