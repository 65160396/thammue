<?php
// /exchangepage/api/_config.php
declare(strict_types=1);

/* =========================================================
 * Thammue: API bootstrap (dev @ localhost:8000)
 * - CORS (localhost/127.0.0.1)
 * - Session แชร์ทั้งไซต์ (path='/') เพื่อใช้ล็อกอินเดียวกับเพื่อน
 * - PDO MySQL + JSON helpers + upload helpers
 * ========================================================= */

if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

/* ---------- CORS (dev) ---------- */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = $origin && preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~i', $origin);
header('Access-Control-Allow-Origin: ' . ($allow ? $origin : 'http://localhost:8000'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

/* ---------- DEV toggle ---------- */
const DEV = true;
if (DEV) { error_reporting(E_ALL); ini_set('display_errors', '1'); }
else { error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); ini_set('display_errors','0'); }

/* ---------- Session (shared) ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('PHPSESSID');               // ให้ตรงกับเว็บหลัก
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',                     // แชร์คุกกี้ทั้งไซต์/ทุกโฟลเดอร์
    'secure'   => false,                   // dev (http)
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

/* ---------- DB config ---------- */
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';

$DB_NAME = 'thammue';   // ฐานข้อมูลของฝั่งแลกเปลี่ยน (items, item_images, ...)
// << ใช้ users จริงจากฐานเพื่อน >>
const USERS_DB = 'shopdb';

function db(): PDO {
  static $pdo;
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  if (!$pdo) {
    $pdo = new PDO(
      "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
      $DB_USER, $DB_PASS,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
      ]
    );
  }
  return $pdo;
}

/** ping DB แบบเบา ๆ ใช้ในหน้า API เพื่อยืนยันว่าเชื่อมได้จริง */
function assert_db_alive(PDO $pdo): void {
  try { $pdo->query('SELECT 1'); }
  catch (Throwable $e) { json_err('db_connect_failed', 500, ['msg' => $e->getMessage()]); }
}

/* ---------- Paths ---------- */
define('THAMMUE_BASE', '/exchangepage');                 // public base
define('PUBLIC_DIR', dirname(__DIR__) . '/public');      // …/exchangepage/public
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');           // …/exchangepage/public/uploads

/* ---------- JSON helpers ---------- */
function json_ok($data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(is_array($data) ? (['ok'=>true] + $data) : ['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  $p = ['ok'=>false, 'error'=>$message]; if ($extra) $p['extra']=$extra;
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---------- Auth helpers ---------- */
function me_id(): int { return (int)($_SESSION['user_id'] ?? 0); }
function require_login(): int { $id = me_id(); if ($id<=0) json_err('unauthorized', 401); return $id; }

/* ---------- FS & Upload helpers ---------- */
function ensure_dir(string $path): void { if (!is_dir($path)) @mkdir($path, 0777, true); }
function public_url(?string $relPath): ?string { return $relPath? rtrim(THAMMUE_BASE,'/').'/'.ltrim($relPath,'/'): null; }
if (!function_exists('pub_url')) { function pub_url(?string $relPath): ?string { return public_url($relPath); } }

/** save multiple images from input name="images[]" */
function save_uploaded_images(array $files, string $subdir = 'items'): array {
  $target = rtrim(UPLOAD_DIR,'/').'/'.trim($subdir,'/');
  ensure_dir($target);
  $saved=[]; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf'];
  $n = isset($files['name']) ? count($files['name']) : 0;
  for ($i=0; $i<$n; $i++) {
    $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE; if ($err!==UPLOAD_ERR_OK) continue;
    $tmp = $files['tmp_name'][$i] ?? ''; if (!$tmp || !is_uploaded_file($tmp)) continue;
    $type = @mime_content_type($tmp) ?: ''; if (!isset($allowed[$type])) continue;
    $name = bin2hex(random_bytes(8)).'.'.$allowed[$type];
    if (@move_uploaded_file($tmp, $target.'/'.$name)) $saved[]=$name;
  }
  return $saved;
}
