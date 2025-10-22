<?php
// /exchangepage/api/auth/me.php
declare(strict_types=1);

require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();
$uid = me_id(); // อ่านจาก $_SESSION['user_id'] ที่ถูกแชร์กับเว็บเพื่อน
if ($uid <= 0) {
  json_err('not_logged_in', 401);
}

/* -------------------------------------------------------
 * Helpers
 * -----------------------------------------------------*/

/** ตรวจว่ามีตารางอยู่ใน schema (database) นั้น ๆ หรือไม่ */
function table_exists_db(PDO $pdo, string $schema, string $table): bool {
  $sql = "SELECT 1
          FROM information_schema.tables
          WHERE table_schema = :s AND table_name = :t
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':s'=>$schema, ':t'=>$table]);
  return (bool)$st->fetchColumn();
}

/* -------------------------------------------------------
 * Resolve ตารางที่ต้องใช้ (users / user_profiles)
 * -----------------------------------------------------*/

// _config.php กำหนดค่าเหล่านี้ไว้
global $DB_NAME;                    // DB หลักของฝั่ง exchangepage (เช่น thammue)
$usersDb = defined('USERS_DB') ? USERS_DB : $DB_NAME;   // DB ที่เก็บ users จริง (เช่น shopdb)
$usersTable = "{$usersDb}.users";

// หา table โปรไฟล์: ให้พยายามใช้ของ DB หลักก่อน (thammue.user_profiles)
// ถ้าไม่มี ค่อยลองใน DB เดียวกับ users (shopdb.user_profiles) ถ้าไม่มีก็ไม่ join
$profilesTable = null;
if (table_exists_db($pdo, $DB_NAME, 'user_profiles')) {
  $profilesTable = "{$DB_NAME}.user_profiles";
} elseif (table_exists_db($pdo, $usersDb, 'user_profiles')) {
  $profilesTable = "{$usersDb}.user_profiles";
}

/* -------------------------------------------------------
 * Query
 * -----------------------------------------------------*/

// ใช้ COALESCE(display_name, name) เพื่อรองรับ schema ต่างกัน
if ($profilesTable) {
  $sql = "
    SELECT
      u.id,
      COALESCE(u.display_name, u.name) AS name,
      u.email,
      NULL AS username,
      NULL AS avatar,
      p.first_name, p.last_name, p.gender, p.phone, p.dob,
      p.addr_line, p.addr_province, p.addr_district, p.addr_subdistrict, p.addr_postcode
    FROM {$usersTable} u
    LEFT JOIN {$profilesTable} p ON p.user_id = u.id
    WHERE u.id = :id
    LIMIT 1
  ";
} else {
  $sql = "
    SELECT
      u.id,
      COALESCE(u.display_name, u.name) AS name,
      u.email,
      NULL AS username,
      NULL AS avatar,
      NULL AS first_name, NULL AS last_name, NULL AS gender, NULL AS phone, NULL AS dob,
      NULL AS addr_line, NULL AS addr_province, NULL AS addr_district, NULL AS addr_subdistrict, NULL AS addr_postcode
    FROM {$usersTable} u
    WHERE u.id = :id
    LIMIT 1
  ";
}

$st = $pdo->prepare($sql);
$st->execute([':id' => $uid]);
$row = $st->fetch();

if (!$row) {
  json_err('not_found', 404);
}

/* -------------------------------------------------------
 * Build output
 * -----------------------------------------------------*/

$display = trim((string)($row['name'] ?? ''));
if ($display === '' && !empty($row['username'])) {
  $display = (string)$row['username'];
}
if ($display === '' && !empty($row['email'])) {
  $display = (string)strstr($row['email'], '@', true);
}

json_ok([
  'user' => [
    'id'             => (int)$row['id'],
    'name'           => $row['name'],
    'email'          => $row['email'],
    'username'       => $row['username'],     // คง key ไว้เพื่อเข้ากันได้
    'display_name'   => $display,
    'avatar'         => $row['avatar'],

    'first_name'       => $row['first_name'],
    'last_name'        => $row['last_name'],
    'gender'           => $row['gender'],
    'phone'            => $row['phone'],
    'dob'              => $row['dob'],
    'addr_line'        => $row['addr_line'],
    'addr_province'    => $row['addr_province'],
    'addr_district'    => $row['addr_district'],
    'addr_subdistrict' => $row['addr_subdistrict'],
    'addr_postcode'    => $row['addr_postcode'],
  ],
  'ts' => time(),
]);
