<?php
require __DIR__ . '/../_config.php';

$pdo = db();
$uid = me_id(); if(!$uid) json_err('AUTH', 401);

$st = $pdo->prepare("
  SELECT c.id,
         i.title AS item_title,
         CASE WHEN c.user_a=:u THEN u2.display_name ELSE u1.display_name END AS other_name,
         (SELECT body FROM messages m WHERE m.conv_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_body
  FROM conversations c
  LEFT JOIN users u1 ON u1.id=c.user_a
  LEFT JOIN users u2 ON u2.id=c.user_b
  LEFT JOIN items i ON i.id=c.item_id
  WHERE c.user_a=:u OR c.user_b=:u
  ORDER BY c.last_msg_at DESC
  LIMIT 100
");
$st->execute([':u'=>$uid]);
json_ok(['items'=>$st->fetchAll()]);
