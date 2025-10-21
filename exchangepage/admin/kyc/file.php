<?php
require __DIR__ . '/../_config_admin.php';
require_admin();
$project = current_project(); // ปกติ 'exchange'
if ($project !== 'exchange') { http_response_code(403); exit; }

$base = __DIR__ . '/../../uploads/kyc/exchange';
$name = basename($_GET['name'] ?? '');
$path = $base . '/' . $name;
if (!$name || !is_file($path)) { http_response_code(404); exit; }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header("Content-Type: $mime");
header('Content-Length: ' . filesize($path));
readfile($path);
