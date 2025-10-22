<?php
require_once __DIR__ . '/ex__common.php';
$mysqli = dbx();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();
if (!$uid) jerr('not_logged_in', 401);

// Rooms list for current user
$rooms = [];
$stmt = $mysqli->prepare("
  SELECT r.id, r.title, r.updated_at
  FROM ex_chat_rooms r
  JOIN ex_chat_participants p ON p.room_id=r.id
  WHERE p.user_id=?
  ORDER BY r.updated_at DESC
  LIMIT 200
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$rooms = stmt_all_assoc($stmt);

// If specific room requested, return messages
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$messages = [];
if ($room_id > 0) {
  // verify membership
  $chk = $mysqli->prepare("SELECT 1 FROM ex_chat_participants WHERE user_id=? AND room_id=?");
  $chk->bind_param("ii", $uid, $room_id);
  $chk->execute();
  $ok = $chk->get_result()->fetch_row();
  if (!$ok) jerr('forbidden', 403);

  $stmt = $mysqli->prepare("
    SELECT m.id, m.user_id, m.body, m.created_at, (m.user_id = ?) AS is_me
    FROM ex_chat_messages m
    WHERE m.room_id=?
    ORDER BY m.id ASC
    LIMIT 500
  ");
  $stmt->bind_param("ii", $uid, $room_id);
  $stmt->execute();
  $messages = stmt_all_assoc($stmt);
}

echo json_encode(['ok'=>true,'rooms'=>$rooms,'messages'=>$messages], JSON_UNESCAPED_UNICODE);
