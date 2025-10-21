<?php
require __DIR__ . '/../_config.php';

$pdo = db();
$uid = me_id(); $cid = (int)($_GET['conv_id'] ?? 0);
if(!$uid || !$cid) json_err('BAD_REQ', 400);

$st = $pdo->prepare("
  SELECT c.*, i.title AS item_title, u1.display_name AS a_name, u2.display_name AS b_name
  FROM conversations c
  LEFT JOIN items i ON i.id=c.item_id
  LEFT JOIN users u1 ON u1.id=c.user_a
  LEFT JOIN users u2 ON u2.id=c.user_b
  WHERE c.id=:c AND (c.user_a=:u OR c.user_b=:u)
  LIMIT 1
");
$st->execute([':c'=>$cid, ':u'=>$uid]);
$cv = $st->fetch(); if(!$cv) json_err('NOT_FOUND', 404);

$other = ((int)$cv['user_a']===$uid) ? ($cv['b_name'] ?? 'อีกฝ่าย') : ($cv['a_name'] ?? 'อีกฝ่าย');
json_ok(['other_name'=>$other,'item_title'=>$cv['item_title'] ?? null]);
