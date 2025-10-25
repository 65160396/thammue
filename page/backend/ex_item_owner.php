<?php
// /page/backend/ex_item_owner.php — fixed (no SHOW COLUMNS LIKE ?)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($ok, $data = []) {
  echo json_encode(array_merge(['ok'=>$ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}
function fail($msg, $code=400){ http_response_code($code); out(false, ['error'=>$msg]); }

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId <= 0) fail('bad_id');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  // เชื่อม 2 ฐาน: shopdb_ex (ของแลกเปลี่ยน), shopdb (ผู้ใช้หลัก)
  $ex   = new mysqli('127.0.0.1','root','', 'shopdb_ex'); $ex->set_charset('utf8mb4');
  $main = new mysqli('127.0.0.1','root','', 'shopdb');   $main->set_charset('utf8mb4');
} catch (Throwable $e) { fail('db_connect_failed', 500); }

// 1) หาผู้ครอบครองสินค้าจาก ex_items
try {
  $st = $ex->prepare("SELECT user_id FROM ex_items WHERE id=? LIMIT 1");
  $st->bind_param("i", $itemId);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  if (!$row) fail('not_found', 404);
  $ownerId = (int)$row['user_id'];
} catch (Throwable $e) { fail('ex_items_query_failed', 500); }

// util: ตรวจว่าคอลัมน์มีอยู่จริง (ผ่าน INFORMATION_SCHEMA)
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $q = $db->prepare($sql);
  $q->bind_param("ss", $table, $col);
  $q->execute();
  return (bool)$q->get_result()->fetch_row();
}
function table_exists(mysqli $db, string $table): bool {
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          LIMIT 1";
  $q = $db->prepare($sql);
  $q->bind_param("s", $table);
  $q->execute();
  return (bool)$q->get_result()->fetch_row();
}

// 2) ประกอบ display name จากตารางผู้ใช้
$name = null;
if (table_exists($main, 'users')) {
  $parts = [];

  if (table_exists($main, 'user_profiles') && col_exists($main,'user_profiles','full_name')) {
    $parts[] = "up.full_name";
  }
  if (col_exists($main,'users','display_name')) $parts[] = "u.display_name";
  if (col_exists($main,'users','name'))         $parts[] = "u.name";
  if (col_exists($main,'users','first_name') && col_exists($main,'users','last_name')) {
    $parts[] = "CONCAT(u.first_name,' ',u.last_name)";
  }
  if (col_exists($main,'users','email'))        $parts[] = "u.email";

  // อย่างน้อยต้องมีอย่างหนึ่ง ถ้าไม่มีเลย ใช้รหัสผู้ใช้เป็นสำรอง
  if (!$parts) $parts[] = "CAST(u.id AS CHAR)";

  $coalesce = 'COALESCE('.implode(',', $parts).')';

  try {
    $sql = "SELECT $coalesce AS owner_name
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE u.id=? LIMIT 1";
    $st2 = $main->prepare($sql);
    $st2->bind_param("i", $ownerId);
    $st2->execute();
    $u = $st2->get_result()->fetch_assoc();
    if ($u && $u['owner_name']) $name = $u['owner_name'];
  } catch (Throwable $e) {
    // ปล่อยให้ fallback ด้านล่างทำงาน
  }
}

// fallback
if ($name === null) $name = 'ผู้ใช้ #'.$ownerId;

// ส่งผลลัพธ์
out(true, ['owner_id'=>$ownerId, 'owner_name'=>$name]);
