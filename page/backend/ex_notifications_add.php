<?php
// /page/backend/ex_notifications_add.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user_id = (int)($_POST['user_id'] ?? 0);
$type    = (string)($_POST['type'] ?? 'status');
$ref_id  = (int)($_POST['ref_id'] ?? 0);
$title   = trim((string)($_POST['title'] ?? ''));
$body    = trim((string)($_POST['body'] ?? ''));

if ($user_id<=0 || $title==='') jerr('bad_params',400);

$st = $mysqli->prepare("INSERT INTO ex_notifications (user_id,type,ref_id,title,body,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
$st->bind_param("isiss", $user_id,$type,$ref_id,$title,$body);
$st->execute();

jok();
