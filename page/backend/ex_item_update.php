<?php
require_once __DIR__ . '/ex__items_common.php';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
if ($id<=0) jerr('bad_request');

// ownership
$chk = $mysqli->prepare("SELECT user_id FROM items WHERE id=? LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$own = $chk->get_result()->fetch_assoc();
if (!$own) jerr('not_found', 404);
if ((int)$own['user_id'] !== $uid) jerr('forbidden', 403);

$title = trim((string)($input['title'] ?? ''));
$price = $input['price'] ?? null;
$description = trim((string)($input['description'] ?? ''));
$thumb = trim((string)($input['thumbnail_url'] ?? ''));

$cols = item_columns($mysqli);
$sets = []; $types=''; $bindVals=[];

if ($title!==''){ $sets[]='title=?'; $types.='s'; $bindVals[]=$title; }
if (in_array('description',$cols)){ $sets[]='description=?'; $types.='s'; $bindVals[]=$description; }
if (in_array('price',$cols) && $price!==null){ $sets[]='price=?'; $types.='d'; $bindVals[]=$price; }
if (in_array('thumbnail_url',$cols) && $thumb!==''){ $sets[]='thumbnail_url=?'; $types.='s'; $bindVals[]=$thumb; }
if (in_array('updated_at',$cols)){ $sets[]='updated_at=NOW()'; }

if (!count($sets)) jerr('nothing_to_update');

$sql = "UPDATE items SET " . implode(',', $sets) . " WHERE id=?";
$types .= 'i'; $bindVals[]=$id;
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$bindVals);
$st->execute();
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
