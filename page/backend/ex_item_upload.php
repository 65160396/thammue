<?php
// /page/backend/ex_item_upload.php
require_once __DIR__ . '/ex__common.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$uid) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'error'=>'no_file']); exit; }

$f = $_FILES['file'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp','gif'];
if (!in_array($ext, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'bad_type']); exit; }

$root = realpath(__DIR__ . '/../../');
$dir = $root . '/uploads/items';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

$name = 'item_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$dest = $dir . '/' . $name;
if (!move_uploaded_file($f['tmp_name'], $dest)) { echo json_encode(['ok'=>false,'error'=>'cannot_move']); exit; }
$url = '/uploads/items/' . $name;
echo json_encode(['ok'=>true,'url'=>$url], JSON_UNESCAPED_UNICODE);
