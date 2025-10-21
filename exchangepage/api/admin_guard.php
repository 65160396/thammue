<?php
require __DIR__ . '/config.php';
function admin_require_api(){
  if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('login'); }
  global $pdo;
  $st = $pdo->prepare("SELECT is_admin,status FROM users WHERE id=:id LIMIT 1");
  $st->execute([':id'=>(int)$_SESSION['user_id']]);
  $u = $st->fetch();
  if (!$u || !$u['is_admin'] || $u['status']!=='active') { http_response_code(403); exit('forbidden'); }
  if (empty($_SESSION['_csrf_admin'])) $_SESSION['_csrf_admin']=bin2hex(random_bytes(16));
  if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['_csrf_admin'], $_POST['_csrf'])) {
    http_response_code(403); exit('CSRF');
  }
}
