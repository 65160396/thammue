<?php
// /page/backend/admin/logout.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['admin_id'] = null;
$_SESSION['admin_name'] = null;
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true]);
