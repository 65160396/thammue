<?php
require_once __DIR__ . '/ex__common.php';

/** ใช้ฐาน shopdb_ex (ฝั่งแลกเปลี่ยน) */
function dbx_ex(): mysqli {
  $m = new mysqli('127.0.0.1', 'root', '', 'shopdb_ex');
  if ($m->connect_error) {
    http_response_code(500);
    die(json_encode(['ok'=>false,'error'=>'db_connect_failed']));
  }
  $m->set_charset('utf8mb4');
  return $m;
}

$mysqli = dbx_ex();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = me();

/** ถ้าไฟล์ไหนต้องล็อกอิน ให้ตั้ง $REQUIRE_LOGIN = true ก่อน include */
if (!isset($REQUIRE_LOGIN)) $REQUIRE_LOGIN = false;
if ($REQUIRE_LOGIN && !$uid) jerr('not_logged_in', 401);

/** ชื่อตารางสินค้าฝั่งแลกเปลี่ยน */
if (!defined('EX_ITEMS_TABLE')) {
  define('EX_ITEMS_TABLE', 'ex_items');
}

/** คืนรายชื่อคอลัมน์ของตารางสินค้าที่ใช้จริง */
function item_columns(mysqli $m): array {
  $dbRes = $m->query("SELECT DATABASE()");
  $db = $dbRes ? ($dbRes->fetch_row()[0] ?? '') : '';
  $cols = [];
  $sql = "SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=?";
  $table = EX_ITEMS_TABLE;
  $st = $m->prepare($sql);
  $st->bind_param("ss", $db, $table);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];
  return $cols;
}

/* สำคัญ: อย่า duplicate jerr() ที่นี่ — ใช้ของ ex__common.php เท่านั้น */
