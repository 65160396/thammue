<?php
// /thammue/api/items/images.delete.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) json_err('UNAUTH', 401);

$itemId = (int)($_POST['item_id'] ?? 0);
$imgId  = (int)($_POST['image_id'] ?? 0);
if ($itemId<=0 || $imgId<=0) json_err('MISSING_PARAM', 422);

// ตรวจสิทธิ์เจ้าของ
$own = $pdo->prepare("SELECT user_id FROM items WHERE id=:id");
$own->execute([':id'=>$itemId]);
$owner = (int)$own->fetchColumn();
if (!$owner) json_err('NOT_FOUND', 404);
if ($owner !== $uid) json_err('FORBIDDEN', 403);

// ดึง path
$st = $pdo->prepare("SELECT path FROM item_images WHERE id=:img AND item_id=:item");
$st->execute([':img'=>$imgId, ':item'=>$itemId]);
$path = $st->fetchColumn();
if (!$path) json_err('NOT_FOUND', 404);

// ลบไฟล์จริง (ถ้ามี)
$abs = realpath(__DIR__ . '/../../' . $path);
if ($abs && is_file($abs)) @unlink($abs);

// ลบแถว
$pdo->prepare("DELETE FROM item_images WHERE id=:img AND item_id=:item")->execute([':img'=>$imgId, ':item'=>$itemId]);

json_ok(['ok'=>true]);
