<?php
// /exchangepage/api/verification/status.php
require __DIR__ . '/../_config.php';

$pdo = db();
$uid = me_id();
if (!$uid) json_err('not_login', 401);

$st = $pdo->prepare("
  SELECT status
  FROM verifications
  WHERE user_id = :u
  ORDER BY id DESC
  LIMIT 1
");
$st->execute([':u'=>$uid]);
$row = $st->fetch();

$verified = ($row && isset($row['status']) && $row['status'] === 'verified');
json_ok(['verified' => $verified]);
