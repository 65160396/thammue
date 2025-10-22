<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

if (!isset($_FILES['image'])) jerr('no_file');
$f = $_FILES['image'];
if ($f['error'] !== UPLOAD_ERR_OK) jerr('upload_error');

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp','gif'];
if (!in_array($ext, $allowed)) jerr('bad_type');

$root = realpath(__DIR__ . '/../../'); // project root (assumed)
$dir = $root . '/uploads/items';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }

$fname = 'item_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$dest = $dir . '/' . $fname;
if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('cannot_move');

// public URL (adjust if you deploy in subfolder)
$url = '/uploads/items/' . $fname;
echo json_encode(['ok'=>true,'url'=>$url], JSON_UNESCAPED_UNICODE);
