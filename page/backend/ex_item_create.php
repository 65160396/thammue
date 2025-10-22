<?php
require_once __DIR__ . '/ex__items_common.php';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$title = trim((string)($input['title'] ?? ''));
$price = $input['price'] ?? null;
$description = trim((string)($input['description'] ?? ''));
$thumb = trim((string)($input['thumbnail_url'] ?? ''));
if ($title==='') jerr('title_required');

$cols = item_columns($mysqli);
$nowCols = array_intersect($cols, ['created_at','updated_at']);
$fields = ['user_id','title']; $params = ['ii','user_id'=>$uid, 'title'=>$title]; $types='i'; $bind = [];

$fields = ['user_id','title'];
$values = ['?','?'];
$types = 'is';
$bindVals = [$uid, $title];

if (in_array('description',$cols)) { $fields[]='description'; $values[]='?'; $types.='s'; $bindVals[]=$description; }
if (in_array('price',$cols) && $price!==null) { $fields[]='price'; $values[]='?'; $types.='d'; $bindVals[]=$price; }
if (in_array('thumbnail_url',$cols) && $thumb!=='') { $fields[]='thumbnail_url'; $values[]='?'; $types.='s'; $bindVals[]=$thumb; }
if (in_array('created_at',$cols)) { $fields[]='created_at'; $values[]='NOW()'; }
if (in_array('updated_at',$cols)) { $fields[]='updated_at'; $values[]='NOW()'; }

$sql = "INSERT INTO items (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$bindVals);
$st->execute();
$id = $mysqli->insert_id;
echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
