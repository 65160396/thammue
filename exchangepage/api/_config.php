<?php
// /thammue/api/_config.php

// ===========================
// HTTP Headers & CORS
// ===========================
// หมายเหตุ: ห้ามใช้ Access-Control-Allow-Origin: * คู่กับ credentials
header('Content-Type: application/json; charset=utf-8');

// สะท้อน Origin ที่เรียกมา เพื่อให้ browser อนุญาตส่งคุกกี้/เซสชัน
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== '') {
  header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
  // same-origin (เช่นเปิดหน้า /thammue/public/ จาก http://localhost)
  header('Access-Control-Allow-Origin: http://localhost');
}
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// จัดการ preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===========================
// Session
// ===========================
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ===========================
// DB Config
// ===========================
$DB_HOST = '127.0.0.1';
$DB_NAME = 'thammue';
$DB_USER = 'root';
$DB_PASS = '';

// พาธ base ของโปรเจกต์ (ใช้สร้าง public URL ให้ไฟล์อัปโหลด)
define('THAMMUE_BASE', '/thammue'); // แก้ให้ตรงเครื่องถ้าจำเป็น

function db(): PDO {
  static $pdo;
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  if (!$pdo) {
    $pdo = new PDO(
      "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
      $DB_USER,
      $DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  }
  return $pdo;
}

// ===========================
// Auth helpers
// ===========================
function me_id(): int { return (int)($_SESSION['user_id'] ?? 0); }

function require_login(): int {
  $uid = me_id();
  if (!$uid) json_err('unauthorized', 401);
  return $uid;
}

// ===========================
// JSON helpers
// ===========================
/**
 * json_ok:
 * - ถ้า $data เป็น array => merge เข้ากับ ['ok'=>true]
 * - ถ้า $data ไม่ใช่ array => คืน ['ok'=>true, 'data'=>$data]
 */
function json_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (is_array($data)) {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  } else {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

function json_err(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message, 'extra' => $extra], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===========================
// Filesystem helpers
// ===========================
function ensure_dir(string $path): void {
  if (!is_dir($path)) { mkdir($path, 0777, true); } // dev: 0777 ง่ายสุดบน XAMPP
}

/**
 * แปลง path ที่เก็บใน DB (เช่น 'uploads/items/a.jpg') เป็น public URL
 * ตัวหลักใช้ชื่อ public_url(); และทำ alias pub_url() ไว้ให้ไฟล์อื่นเรียกได้
 */
function public_url(string $relPath): string {
  $relPath = '/' . ltrim($relPath, '/');
  return rtrim(THAMMUE_BASE, '/') . $relPath; // => /thammue/uploads/items/a.jpg
}

// alias กันพังกับไฟล์ที่เรียกใช้ชื่อเดิม
if (!function_exists('pub_url')) {
  function pub_url(?string $relPath): ?string {
    if ($relPath === null || $relPath === '') return null;
    return public_url($relPath);
  }
}

/**
 * เซฟรูปหลายไฟล์: คืนชื่อไฟล์ (ไม่รวมโฟลเดอร์) ที่เซฟได้
 * - ตรวจ MIME ด้วย mime_content_type
 * - อนุญาต jpg/png/webp/gif
 */
function save_uploaded_images(array $files, string $targetDir): array {
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

    $tmp  = $files['tmp_name'][$i] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) continue;

    $type = @mime_content_type($tmp);
    if (!isset($allowed[$type])) continue;

    $ext = $allowed[$type];
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = rtrim($targetDir, '/').'/'.$name;

    if (move_uploaded_file($tmp, $dest)) {
      $saved[] = $name;
    }
  }
  return $saved;
}
