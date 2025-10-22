<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

$st = $mysqli->prepare("SELECT status FROM ex_user_kyc WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->bind_param("i", $uid);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$status = $row ? $row['status'] : 'none';
echo json_encode(['ok'=>true,'status'=>$status], JSON_UNESCAPED_UNICODE);
