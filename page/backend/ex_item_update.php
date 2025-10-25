<?php
// /page/backend/ex_item_update.php
require_once __DIR__ . '/ex__items_common.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = $uid ?? me(); // จาก ex__common.php
if (!$uid) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$itemId = (int)($input['id'] ?? 0);
$title  = trim((string)($input['title'] ?? ''));
$desc   = trim((string)($input['description'] ?? ''));
$thumb  = trim((string)($input['thumbnail_url'] ?? ''));

if ($itemId <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$table = EX_ITEMS_TABLE;
$cols  = item_columns($mysqli);
$has   = function($c) use ($cols){ return in_array($c,$cols,true); };

// เจ้าของสินค้า?
$st = $mysqli->prepare("SELECT user_id FROM `$table` WHERE id=?");
$st->bind_param('i', $itemId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
if ((int)$row['user_id'] !== (int)$uid) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

// สร้างชุดอัปเดตเฉพาะฟิลด์ที่มีค่าจริง และคอลัมน์มีอยู่
$sets = []; $types=''; $args=[];
if ($title !== '' && $has('title')) { $sets[]='title=?'; $types.='s'; $args[]=$title; }
if ($desc  !== '' && $has('description')) { $sets[]='description=?'; $types.='s'; $args[]=$desc; }
if ($thumb !== '' && $has('thumbnail_url')) { $sets[]='thumbnail_url=?'; $types.='s'; $args[]=$thumb; }
if ($has('updated_at')) { $sets[]='updated_at=NOW()'; } // ไม่ต้อง bind

if (!$sets) { echo json_encode(['ok'=>true]); exit; }

$sql = "UPDATE `$table` SET ".implode(',', $sets)." WHERE id=?";
$types .= 'i'; $args[] = $itemId;

$st2 = $mysqli->prepare($sql);
$st2->bind_param($types, ...$args);
$st2->execute();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
