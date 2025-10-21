<?php
// /thammue/exchangepage/api/_config.php

// ---------- HTTP headers & CORS ----------
header('Content-Type: application/json; charset=utf-8');

// สะท้อน origin เพื่อให้ส่งคุกกี้ได้ (อย่าใช้ * ตอนมี Credentials)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
  // อนุญาตเฉพาะ localhost ของ dev
  if (preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
  } else {
    header('Access-Control-Allow-Origin: http://localhost:8000');
  }
} else {
  header('Access-Control-Allow-Origin: http://localhost:8000');
}
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------- Session ----------
$cookie_secure = false; // dev http
$cookie_httponly = true;
$cookie_samesite = 'Lax';
// ทำ path ให้ตรงกับ base (/thammue/exchangepage) เพื่อให้หน้าเว็บส่งคุกกี้ติดมาถูกที่
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/thammue/exchangepage',
  'domain' => '',        // localhost
  'secure' => $cookie_secure,
  'httponly' => $cookie_httponly,
  'samesite' => $cookie_samesite
]);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ---------- DB ----------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'thammue';
$DB_USER = 'root';
$DB_PASS = '';

function db(): PDO {
  static $pdo;
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  if (!$pdo) {
    $pdo = new PDO(
      "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
      $DB_USER, $DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  }
  return $pdo;
}

// ---------- Base paths ----------
define('THAMMUE_BASE', '/thammue/exchangepage');          // public base สำหรับสร้าง URL
define('PUBLIC_DIR', dirname(__DIR__) . '/public');       // โฟลเดอร์ public ของ exchangepage
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');            // เก็บอัปโหลดใน exchangepage/public/uploads

// ---------- JSON helpers ----------
function json_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (is_array($data)) echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
  else echo json_encode(['ok'=>true, 'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$message, 'extra'=>$extra], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- Auth helpers ----------
function me_id(): int { return (int)($_SESSION['user_id'] ?? 0); }
function require_login(): int {
  $uid = me_id();
  if (!$uid) json_err('unauthorized', 401);
  return $uid;
}

// ---------- FS helpers ----------
function ensure_dir(string $path): void {
  if (!is_dir($path)) mkdir($path, 0777, true);
}
// แปลง relative path -> public URL (/thammue/exchangepage/xxx)
function public_url(?string $relPath): ?string {
  if ($relPath === null || $relPath === '') return null;
  $relPath = '/' . ltrim($relPath, '/');
  return rtrim(THAMMUE_BASE, '/') . $relPath;
}
if (!function_exists('pub_url')) {
  function pub_url(?string $relPath): ?string { return public_url($relPath); }
}

// เซฟหลายรูป: คืนชื่อไฟล์ที่บันทึกได้ (เก็บไฟล์ไว้ใต้ UPLOAD_DIR)
function save_uploaded_images(array $files, string $subdir = 'items'): array {
  $targetDir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subdir, '/');
  ensure_dir($targetDir);
  $saved = [];
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];
  $n = isset($files['name']) ? count($files['name']) : 0;
  for ($i = 0; $i < $n; $i++) {
    $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) continue;

    $tmp = $files['tmp_name'][$i] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) continue;

    $type = @mime_content_type($tmp);
    if (!isset($allowed[$type])) continue;

    $ext = $allowed[$type];
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $targetDir . '/' . $name;

    if (move_uploaded_file($tmp, $dest)) $saved[] = $name;
  }
  return $saved;
}
