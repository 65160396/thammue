<?php
// /thammue/admin/_config_admin.php
header('Content-Type: application/json; charset=utf-8');

// --- CORS/Same-origin (dev) ---
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Project-Key');
if (isset($_SERVER['HTTP_ORIGIN'])) {
  header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
  header('Access-Control-Allow-Origin: http://localhost');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// --- ADMIN session (cookie แยกจากผู้ใช้) ---
function _apply_admin_cookie(): void {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $params = ['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'];
  if (PHP_VERSION_ID >= 70300) session_set_cookie_params($params);
  else session_set_cookie_params($params['lifetime'], $params['path'].'; samesite='.$params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
  session_name('THAMMUE_ADM');
}
function start_admin_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) { _apply_admin_cookie(); session_start(); }
}

// --- Multi-DB mapping ---
$DB_MAP = [
  'exchange' => ['host'=>'127.0.0.1','name'=>'thammue','user'=>'root','pass'=>''],
  'shop'     => ['host'=>'127.0.0.1','name'=>'shopdb',  'user'=>'root','pass'=>''],
];

function db_for(string $project): PDO {
  static $pool = [];
  global $DB_MAP;
  $p = strtolower($project);
  if (!isset($DB_MAP[$p])) json_err('unknown_project', 400, ['project'=>$project]);
  if (!isset($pool[$p])) {
    $cfg = $DB_MAP[$p];
    $pool[$p] = new PDO(
      "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
      $cfg['user'], $cfg['pass'],
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
  }
  return $pool[$p];
}

// --- Auth/CSRF ---
function admin_id(): int { start_admin_session(); return (int)($_SESSION['admin_id'] ?? 0); }
function require_admin(): int { $aid = admin_id(); if(!$aid) json_err('unauthorized',401); return $aid; }
function ensure_csrf_token(): string {
  start_admin_session();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function verify_csrf(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'GET') return;
  start_admin_session();
  $client = $_SERVER['HTTP_X_CSRF-Token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? ''));
  if (!$client || !hash_equals($_SESSION['csrf'] ?? '', $client)) json_err('bad_csrf', 419);
}

// --- Project key helpers ---
function current_project(): string {
  $key = $_SERVER['HTTP_X_PROJECT_KEY'] ?? ($_GET['project_key'] ?? '');
  if (!$key) json_err('missing_project_key', 400);
  return preg_replace('/[^a-z0-9_\-]/i','', $key);
}

// --- JSON helpers ---
function json_ok($data=[], int $code=200): void {
  http_response_code($code);
  echo json_encode(['ok'=>true] + (is_array($data)?$data:['data'=>$data]), JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $message, int $code=400, array $extra=[]): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$message,'extra'=>$extra], JSON_UNESCAPED_UNICODE);
  exit;
}
