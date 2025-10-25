<?php
// /page/backend/ex_kyc_upload.php
require_once __DIR__ . '/ex__common.php'; // uses shopdb_ex only for helpers
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('method_not_allowed', 405);
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) jerr('no_file');

$f = $_FILES['file'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp'];
if (!in_array($ext, $allowed, true)) jerr('bad_type');

$root = realpath(__DIR__ . '/../../');
$dir = $root . '/uploads/kyc';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }

$fname = 'kyc_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$dest = $dir . '/' . $fname;
if (!move_uploaded_file($f['tmp_name'], $dest)) jerr('cannot_move');

$url = '/uploads/kyc/' . $fname;
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'url'=>$url], JSON_UNESCAPED_UNICODE);
