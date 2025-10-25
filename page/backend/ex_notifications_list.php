<?php
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx();
$uid = me();
if (!$uid) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit; }

$limit  = max(1, min(100, (int)($_GET['limit']  ?? 30)));
$offset = max(0,           (int)($_GET['offset'] ?? 0));

function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st = $db->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  return (bool)($st->get_result()->fetch_row()[0] ?? 0);
}

$hasText    = col_exists($m, 'ex_notifications', 'text');
$hasTitle   = col_exists($m, 'ex_notifications', 'title');
$hasBody    = col_exists($m, 'ex_notifications', 'body');
$hasPayload = col_exists($m, 'ex_notifications', 'payload');

$cols = "id,type,ref_id,is_read,created_at";
if ($hasText)  { $cols .= ", text"; }
if ($hasTitle) { $cols .= ", title"; }
if ($hasBody)  { $cols .= ", body"; }
if ($hasPayload){$cols .= ", payload"; }

$sql = "SELECT $cols FROM ex_notifications WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?";
$st  = $m->prepare($sql);
$st->bind_param("iii", $uid, $limit, $offset);
$st->execute();
$rs = $st->get_result();

$items = [];
while ($r = $rs->fetch_assoc()) {
  // ทำเป็นรูปแบบเดียวกันเสมอ
  $title = $r['title'] ?? ($r['text'] ?? '');
  $body  = $r['body']  ?? null;
  $payload = null;
  if ($hasPayload && isset($r['payload']) && $r['payload'] !== null) {
    $payload = json_decode($r['payload'], true);
  }
  $items[] = [
    'id'        => (int)$r['id'],
    'type'      => (string)$r['type'],
    'ref_id'    => isset($r['ref_id']) ? (int)$r['ref_id'] : null,
    'title'     => $title,
    'body'      => $body,
    'payload'   => $payload,
    'is_read'   => (int)$r['is_read'],
    'created_at'=> $r['created_at'],
  ];
}

[$unread] = $m->query("SELECT COUNT(*) FROM ex_notifications WHERE user_id={$uid} AND is_read=0")->fetch_row();

echo json_encode(['ok'=>true, 'items'=>$items, 'unread'=>(int)$unread], JSON_UNESCAPED_UNICODE);
