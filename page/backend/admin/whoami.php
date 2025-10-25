<?php
// /page/backend/admin/whoami.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true, 'admin'=>(isset($_SESSION['admin_id']) ? ['id'=>$_SESSION['admin_id'], 'name'=>($_SESSION['admin_name'] ?? 'Admin')] : null)]);
