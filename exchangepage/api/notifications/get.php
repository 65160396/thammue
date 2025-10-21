<?php
// /thammue/api/requests/get.php
require __DIR__ . '/../_config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if (!$uid) json_err('AUTH', 401);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_err('BAD_REQ', 400);

/*
 ตารางที่คาดไว้:
 - requests: id, item_id, requester_user_id, requester_item_id (nullable), message, status (pending|accepted|rejected), created_at, decided_at
 - items: id, user_id, title, cover(อาจมี), visibility
 - users: id, email, display_name
*/

$st = $pdo->prepare("
  SELECT
    r.id, r.item_id, r.requester_user_id, r.requester_item_id, r.message, r.status, r.created_at, r.decided_at,

    -- เป้าหมาย (ของเจ้าของ)
    i.user_id       AS owner_id,
    i.title         AS item_title,
    i.cover         AS item_cover,

    -- ของผู้ยื่นข้อเสนอ (optional)
    ri.title        AS req_item_title,
    ri.cover        AS req_item_cover,

    -- ข้อมูลคน
    uo.email        AS owner_email,
    uo.display_name AS owner_name,
    ur.email        AS requester_email,
    ur.display_name AS requester_name

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

/* อนุญาตให้เห็นเฉพาะ requester หรือ owner */
if ($uid !== (int)$row['owner_id'] && $uid !== (int)$row['requester_user_id']) {
  json_err('FORBIDDEN', 403);
}

/* map output */
$out = [
  'id' => (int)$row['id'],
  'status' => $row['status'],
  'message' => $row['message'],
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

  'target_item' => [ // ของเจ้าของ (ที่ถูกขอ)
    'id'    => (int)$row['item_id'],
    'title' => $row['item_title'],
    'cover' => $row['item_cover'],
  ],
  'requester_item' => [ // ของผู้ขอ (อาจไม่มี)
    'id'    => $row['requester_item_id'] ? (int)$row['requester_item_id'] : null,
    'title' => $row['req_item_title'],
    'cover' => $row['req_item_cover'],
  ],
];

json_ok(['item' => $out]);
