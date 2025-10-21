<?php
// /thammue/exchangepage/api/_config.php
declare(strict_types=1);

/*
|---------------------------------------------------------------------
|  CORS + Headers (รองรับ dev หลายพอร์ต/โดเมน)
|---------------------------------------------------------------------
| หมายเหตุ: ใช้ Allow-Credentials => ห้ามใส่ * ใน Allow-Origin
*/
header('Content-Type: application/json; charset=utf-8');

$origin = '';
if (!empty($_SERVER['HTTP_ORIGIN'])) {
  $origin = $_SERVER['HTTP_ORIGIN'];
} elseif (!empty($_SERVER['HTTP_HOST'])) {
  // same-origin (เช่น 127.0.0.1:8000 หรือ localhost:8000)
  $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
} else {
  // เผื่อกรณีรันผ่าน CLI server โดยไม่มี HOST
  $origin = 'http://127.0.0.1:8000';
}

header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

/*
|---------------------------------------------------------------------
|  Session
|---------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/*
|---------------------------------------------------------------------
|  DB Config (แก้ค่าตรงนี้ให้ตรงเครื่อง)
|---------------------------------------------------------------------
| แนะนำ: เปลี่ยน DB_NAME เป็นของคุณ เช่น 'thammue_exchange'
*/
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'thammue';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

function db(): PDO {
  static $pdo;
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  if ($pdo) return $pdo;
  $pdo = new PDO(
    "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
  // เปิด sql_mode ที่ปลอดภัย (ถ้าต้องการ)
  $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
  return $pdo;
}

/*
|---------------------------------------------------------------------
|  Paths / URLs (สำคัญ: ปรับ BASE ให้ตรงโครงจริงตอนนี้)
|---------------------------------------------------------------------
| THAMMUE_BASE: พาธฐานที่ใช้สร้าง public URL (ตอนนี้โปรเจกต์คุณอยู่ใต้ /thammue/exchangepage)
| UPLOAD_ROOT : path บนดิสก์ของโฟลเดอร์อัปโหลด
*/
define('THAMMUE_BASE', '/thammue/exchangepage'); // <- โครง VS ปัจจุบัน
define('UPLOAD_ROOT', realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads'));

/*
|---------------------------------------------------------------------
|  Helpers (Auth / JSON / Files)
|---------------------------------------------------------------------
*/
function me_id(): int { return (int)($_SESSION['user_id'] ?? 0); }

function require_login(): int {
  $uid = me_id();
  if (!$uid) json_err('unauthorized', 401);
  return $uid;
}

/**
 * ส่ง JSON ok
 * - ถ้า $data เป็น array => merge กับ ['ok'=>true]
 * - ถ้าไม่ใช่ array => ห่อเป็น ['ok'=>true,'data'=>$data]
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

function ensure_dir(string $path): void {
  if (!is_dir($path)) { @mkdir($path, 0777, true); }
}

/**
 * คืน "public URL" จากพาธสัมพัทธ์ใต้โปรเจกต์ exchange
 *  ex) public_url('uploads/items/a.jpg') => /thammue/exchangepage/uploads/items/a.jpg
 */
function public_url(string $relPath): string {
  $relPath = '/' . ltrim($relPath, '/');
  return rtrim(THAMMUE_BASE, '/') . $relPath;
}

/** alias เพื่อไม่ให้ไฟล์เก่าแตก */
if (!function_exists('pub_url')) {
  function pub_url(?string $relPath): ?string {
    if ($relPath === null || $relPath === '') return null;
    return public_url($relPath);
  }
}

/**
 * คืน "filesystem path" ของไฟล์ใต้โฟลเดอร์ uploads (ไว้บันทึกไฟล์ลงดิสก์)
 *  ex) uploads_path('items/a.jpg') => D:\...\thammue\exchangepage\uploads\items\a.jpg
 */
function uploads_path(string $rel): string {
  return rtrim(UPLOAD_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR);
}

/**
 * บันทึกรูปหลายไฟล์จาก $_FILES (รูปแบบ input name="images[]")
 * @param array  $files     $_FILES['images']
 * @param string $subdir    โฟลเดอร์ย่อยใต้ /uploads เช่น 'items' หรือ 'ids'
 * @return array รายชื่อไฟล์ที่บันทึกได้ (เช่น ['abc.jpg', 'def.webp'])
 */
function save_uploaded_images(array $files, string $subdir = 'items'): array {
  $targetDir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . trim($subdir, '/\\');
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

    $type = @mime_content_type($tmp) ?: '';
    if (!isset($allowed[$type])) continue;

    $ext  = $allowed[$type];
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $targetDir . DIRECTORY_SEPARATOR . $name;

    if (@move_uploaded_file($tmp, $dest)) {
      $saved[] = $name;
    }
  }
  return $saved;
}

/*
|---------------------------------------------------------------------
|  Small utilities
|---------------------------------------------------------------------
*/
/** แปลง null/ว่าง ให้เป็น null จริง (ช่วยตอน binding ค่าลง DB) */
function nullify(?string $s): ?string {
  $s = trim((string)$s);
  return $s === '' ? null : $s;
}
