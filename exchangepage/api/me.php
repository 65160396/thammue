<?php
require __DIR__ . '/_config.php';

if (isset($_GET['as'])) { // dev helper: http://localhost/thammue/api/me.php?as=1
  $_SESSION['user_id'] = (int)$_GET['as'];
  json_ok(['user'=>['id'=>$_SESSION['user_id']]], 200);
}

$uid = $_SESSION['user_id'] ?? 0;
if (!$uid) json_err('not logged in', 401);
$pdo = db();
$u = $pdo->prepare("SELECT id, email, display_name FROM users WHERE id=:id");
$u->execute([':id'=>$uid]);
$user = $u->fetch();
if (!$user) json_err('user not found', 404);
json_ok(['user'=>$user]);
