<?php
// /exchangepage/api/notifications/list.php
require_once __DIR__ . '/_guard.php';
$pdo = db();

$limit  = max(1, min(50, (int)($_GET['limit']  ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$stmt = $pdo->prepare("
  SELECT id, type, title, body, link, is_read, created_at
  FROM notifications
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bindValue(1, me(), PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
  'items'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
  'limit'  => $limit,
  'offset' => $offset
], JSON_UNESCAPED_UNICODE);
