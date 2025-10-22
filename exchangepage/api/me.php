 // <?php //
// /thammue/exchangepage/api/me.php
//require __DIR__ . '/_config.php';

// dev helper: สลับ user เร็ว ๆ
// http://127.0.0.1:8000/thammue/exchangepage/api/me.php?as=1
//if (isset($_GET['as'])) {
  //$_SESSION['user_id'] = (int)$_GET['as'];
  //json_ok(['user' => ['id' => (int)$_SESSION['user_id']]], 200);
//}

//$uid = (int)($_SESSION['user_id'] ?? 0);
//if (!$uid) json_err('not logged in', 401);

////$pdo = db();
//$u = $pdo->prepare("SELECT id, email, display_name FROM users WHERE id=:id LIMIT 1");
//$u->execute([':id' => $uid]);
//$user = $u->fetch();

//if (!$user) json_err('user not found', 404);
// json_ok(['user' => $user]); //
