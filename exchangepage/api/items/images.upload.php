<?php
// /exchangepage/api/items/images.upload.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) json_err('UNAUTH', 401);

$itemId = (int)($_POST['item_id'] ?? 0);
if ($itemId <= 0) json_err('MISSING_ITEM_ID', 422);

// owner only
$own = $pdo->prepare("SELECT user_id FROM items WHERE id=:id");
$own->execute([':id'=>$itemId]);
$owner = (int)$own->fetchColumn();
if (!$owner) json_err('NOT_FOUND', 404);
if ($owner !== $uid) json_err('FORBIDDEN', 403);

// sort base
$maxSt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM item_images WHERE item_id=:id");
$maxSt->execute([':id'=>$itemId]);
$baseSort = (int)$maxSt->fetchColumn();

$destBase = __DIR__ . '/../../uploads/items/' . $itemId;
if (!is_dir($destBase)) @mkdir($destBase, 0777, true);

$added = [];
if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
  $files = $_FILES['files'];
  for ($i=0; $i<count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
    $tmp  = $files['tmp_name'][$i];
    $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;

    $fname = uniqid('img_', true) . '.' . $ext;
    $dest  = $destBase . '/' . $fname;
    if (!@move_uploaded_file($tmp, $dest)) continue;

    $relPath = 'uploads/items/' . $itemId . '/' . $fname;

    $ins = $pdo->prepare("INSERT INTO item_images (item_id, path, sort_order) VALUES (:item,:path,:sort)");
    $ins->execute([':item'=>$itemId, ':path'=>$relPath, ':sort'=>++$baseSort]);

    $added[] = ['id'=>(int)$pdo->lastInsertId(), 'url'=> THAMMUE_BASE . '/' . $relPath];
  }
}

json_ok(['ok'=>true, 'added'=>$added]);
