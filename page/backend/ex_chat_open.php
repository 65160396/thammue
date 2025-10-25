<?php
require_once __DIR__ . '/ex__common.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$m = dbx();
$uid = me();
try {
  if (!$uid) jerr('not_logged_in', 401);

  $item_id = (int)($_GET['with_item_id'] ?? 0);
  if ($item_id <= 0) jerr('missing with_item_id');

  // ดึงเจ้าของ + ชื่อสินค้า
  $st = $m->prepare("SELECT id, user_id, title FROM " . T_ITEMS . " WHERE id=? LIMIT 1");
  $st->bind_param("i", $item_id);
  $st->execute();
  $it = stmt_one_assoc($st);
  if (!$it) jerr('item_not_found', 404);

  $owner_id   = (int)$it['user_id'];
  $item_title = (string)($it['title'] ?: ('สินค้า #' . $item_id));

  // ทำ room_key ให้ไม่ซ้ำสำหรับ item+owner+user
  $room_key = sprintf('ex_item:%d:owner:%d:user:%d', $item_id, $owner_id, $uid);

  // มีอยู่แล้วหรือยัง
  $st = $m->prepare("SELECT id FROM " . T_CHAT_ROOMS . " WHERE room_key=? LIMIT 1");
  $st->bind_param("s", $room_key);
  $st->execute();
  $found = stmt_one_assoc($st);
  if ($found) {
    if (function_exists('ob_get_length') && ob_get_length()) {
      ob_clean();
    }
    jok(['room_id' => (int)$found['id'], 'room_name' => $item_title]);
  }

  // ยังไม่มี ⇒ สร้างใหม่ (title = ชื่อสินค้า)
  $st = $m->prepare("INSERT INTO " . T_CHAT_ROOMS . " (title, room_key, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
  $st->bind_param("ss", $item_title, $room_key);
  $st->execute();
  $room_id = $m->insert_id;

  // ใส่ผู้เข้าร่วม 2 ฝั่ง
  $ins = $m->prepare("INSERT IGNORE INTO " . T_CHAT_PARTICIPANTS . " (room_id, user_id) VALUES (?,?), (?,?)");
  $ins->bind_param("iiii", $room_id, $owner_id, $room_id, $uid);
  $ins->execute();

  if (function_exists('ob_get_length') && ob_get_length()) {
    ob_clean();
  }
  jok(['room_id' => (int)$room_id, 'room_name' => $item_title]);
} catch (Throwable $e) {
  jerr('db_error: ' . $e->getMessage(), 500);
}
