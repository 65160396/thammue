<?php
// /thammue/exchangepage/api/admin_guard.php
require __DIR__ . '/_config.php';

function admin_require_api(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

  $uid = (int)($_SESSION['user_id'] ?? 0);
  if (!$uid) { json_err('login', 401); }

  $pdo = db();
  $st = $pdo->prepare("SELECT is_admin, status FROM users WHERE id=:id LIMIT 1");
  $st->execute([':id' => $uid]);
  $u = $st->fetch();

  if (!$u || (int)$u['is_admin'] !== 1 || $u['status'] !== 'active') {
    json_err('forbidden', 403);
  }

  // CSRF token สำหรับหน้าแอดมิน
  if (empty($_SESSION['_csrf_admin'])) {
    $_SESSION['_csrf_admin'] = bin2hex(random_bytes(16));
  }
  // ตรวจ CSRF เฉพาะคำขอที่เป็น POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['_csrf_admin'], $csrf)) {
      json_err('CSRF', 403);
    }
  }
}
