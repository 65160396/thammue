<?php
// /exchangepage/api/requests/get.php
require __DIR__ . '/../_config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if (!$uid) json_err('AUTH', 401);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_err('BAD_REQ', 400);

$st = $pdo->prepare("
  SELECT
    r.id, r.item_id, r.requester_user_id, r.requester_item_id, r.message, r.status, r.created_at, r.decided_at,

    i.user_id       AS owner_id,
    i.title         AS item_title,

    ri.title        AS req_item_title,

    uo.email        AS owner_email,
    uo.display_name AS owner_name,
    ur.email        AS requester_email,
    ur.display_name AS requester_name,

    -- cover ของเรา
    (SELECT path FROM item_images im WHERE im.item_id = r.item_id ORDER BY sort_order,id LIMIT 1) AS item_cover,
    -- cover ของผู้ขอ
    (SELECT path FROM item_images im2 WHERE im2.item_id = r.requester_item_id ORDER BY sort_order,id LIMIT 1) AS req_item_cover

  FROM requests r
  JOIN items   i  ON i.id = r.item_id
  JOIN users   uo ON uo.id = i.user_id
  JOIN users   ur ON ur.id = r.requester_user_id
  LEFT JOIN items ri ON ri.id = r.requester_item_id
  WHERE r.id = :id
  LIMIT 1
");
$st->execute([':id'=>$id]);
$row = $st->fetch();
if (!$row) json_err('NOT_FOUND', 404);

if ($uid !== (int)$row['owner_id'] && $uid !== (int)$row['requester_user_id']) {
  json_err('FORBIDDEN', 403);
}

$out = [
  'id'         => (int)$row['id'],
  'status'     => $row['status'],
  'message'    => $row['message'],
  'created_at' => $row['created_at'],
  'decided_at' => $row['decided_at'],

  'owner' => [
    'id'    => (int)$row['owner_id'],
    'name'  => $row['owner_name'],
    'email' => $row['owner_email'],
  ],
  'requester' => [
    'id'    => (int)$row['requester_user_id'],
    'name'  => $row['requester_name'],
    'email' => $row['requester_email'],
  ],

  'target_item' => [
    'id'    => (int)$row['item_id'],
    'title' => $row['item_title'],
    'cover' => pub_url($row['item_cover'] ?? null),
  ],
  'requester_item' => [
    'id'    => $row['requester_item_id'] ? (int)$row['requester_item_id'] : null,
    'title' => $row['req_item_title'],
    'cover' => pub_url($row['req_item_cover'] ?? null),
  ],
];

json_ok(['item' => $out]);
