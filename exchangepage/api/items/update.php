<?php
// /exchangepage/api/items/update.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) json_err('UNAUTH', 401);

$id          = (int)($_POST['id'] ?? 0);
$title       = trim((string)($_POST['title'] ?? ''));
$category_id = (int)($_POST['category_id'] ?? 0);
$description = (string)($_POST['description'] ?? '');
$province    = trim((string)($_POST['province'] ?? ''));

if ($id <= 0) json_err('MISSING_ID', 422);
if ($title === '' || $category_id <= 0) json_err('INVALID_INPUT', 422);

// owner only
$own = $pdo->prepare('SELECT user_id FROM items WHERE id=:id');
$own->execute([':id'=>$id]);
$ownerId = (int)$own->fetchColumn();
if (!$ownerId) json_err('NOT_FOUND', 404);
if ($ownerId !== $uid) json_err('FORBIDDEN', 403);

// update
$upd = $pdo->prepare('
  UPDATE items
  SET title=:title, category_id=:cat, description=:desc, province=:prov
  WHERE id=:id
');
$upd->execute([
  ':title'=>$title, ':cat'=>$category_id, ':desc'=>$description,
  ':prov'=>($province !== '' ? $province : null), ':id'=>$id,
]);

json_ok(['id'=>$id]);
