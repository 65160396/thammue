<?php
// page/backend/logout.php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /page/index.html?type=success&msg=' . rawurlencode('ออกจากระบบแล้ว'));
exit;
