<?php
// /exchangepage/api/items/delete.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) json_err('UNAUTH', 401);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) json_err('MISSING_ID', 422);

// เจ้าของเท่านั้น
$st = $pdo->prepare('SELECT user_id FROM items WHERE id = :id');
$st->execute([':id'=>$id]);
$ownerId = (int)$st->fetchColumn();
if (!$ownerId) json_err('NOT_FOUND', 404);
if ($ownerId !== $uid) json_err('FORBIDDEN', 403);

function table_exists(PDO $pdo, string $name): bool {
  $q = $pdo->prepare("SHOW TABLES LIKE :t");
  $q->execute([':t'=>$name]);
  return (bool)$q->fetchColumn();
}

try {
  $pdo->beginTransaction();

  // ลบไฟล์รูป + แถว images
  if (table_exists($pdo, 'item_images')) {
    $imgs = $pdo->prepare('SELECT path FROM item_images WHERE item_id=:id');
    $imgs->execute([':id'=>$id]);
    while ($row = $imgs->fetch(PDO::FETCH_ASSOC)) {
      $rel = $row['path'] ?? '';
      if ($rel) {
        $abs = realpath(__DIR__ . '/../../' . ltrim($rel, '/'));
        if ($abs && is_file($abs)) @unlink($abs);
      }
    }
    $pdo->prepare('DELETE FROM item_images WHERE item_id=:id')->execute([':id'=>$id]);
  }

  // ตารางลูกอื่น ๆ ที่อาจโยง
  foreach ([['favorites','item_id'], ['item_reports','item_id'], ['requests','item_id'], ['offers','item_id']] as [$tbl,$col]) {
    if (table_exists($pdo,$tbl)) $pdo->prepare("DELETE FROM {$tbl} WHERE {$col}=:id")->execute([':id'=>$id]);
  }

  // ลบตัวสินค้า
  $pdo->prepare('DELETE FROM items WHERE id=:id')->execute([':id'=>$id]);

  $pdo->commit();
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // FK → soft delete
  $msg = $e->getMessage();
  $isFk = (strpos($msg, 'constraint') !== false) || ($e instanceof PDOException && $e->getCode() === '23000');
  if ($isFk) {
    try {
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE items SET visibility='deleted' WHERE id=:id")->execute([':id'=>$id]);
      $pdo->commit();
      echo json_encode(['ok'=>true, 'soft_deleted'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    } catch (Throwable $e2) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      json_err('SERVER_ERROR', 500);
    }
  }
  json_err('SERVER_ERROR', 500);
}
