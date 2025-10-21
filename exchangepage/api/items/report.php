<?php
// /thammue/api/items/report.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();
$uid = $_SESSION['user_id'] ?? null; // จะอนุญาต anonymous ก็ได้

$id = (int)($_POST['id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($id <= 0) json_err('MISSING_ID', 422);
if ($reason === '') json_err('MISSING_REASON', 422);

// ตรวจว่า item มีจริง
$exists = $pdo->prepare('SELECT 1 FROM items WHERE id = :id');
$exists->execute([':id'=>$id]);
if (!$exists->fetchColumn()) json_err('NOT_FOUND', 404);

// บันทึก
$st = $pdo->prepare('INSERT INTO item_reports (item_id, user_id, reason, created_at) VALUES (:item, :uid, :reason, NOW())');
$st->execute([
  ':item'   => $id,
  ':uid'    => $uid,  // อาจเป็น null ได้
  ':reason' => mb_substr($reason, 0, 500),
]);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
