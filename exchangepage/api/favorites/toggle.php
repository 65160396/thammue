<?php
// /exchangepage/api/favorites/toggle.php
require __DIR__ . '/../_config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if(!$uid) json_err('AUTH', 401);

// รองรับ JSON body
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  if (is_array($j)) $_POST = $j + $_POST;
}

$itemId = (int)($_POST['item_id'] ?? 0);
$action = (string)($_POST['action'] ?? 'toggle');
if ($itemId <= 0) json_err('BAD_ID', 400);

// เช็คว่าสินค้ามีและมองเห็นได้
$st = $pdo->prepare("SELECT id FROM items WHERE id=:id AND visibility IN ('public','pending') LIMIT 1");
$st->execute([':id'=>$itemId]);
if (!$st->fetch()) json_err('NOT_FOUND', 404);

// helper insert (รองรับสคีมาที่ไม่มี created_at)
$insertFav = function(int $u,int $i) use($pdo){
  try {
    $pdo->prepare("INSERT INTO favorites (user_id,item_id,created_at) VALUES (:u,:i,NOW())")
        ->execute([':u'=>$u,':i'=>$i]);
  } catch (PDOException $e) {
    $pdo->prepare("INSERT INTO favorites (user_id,item_id) VALUES (:u,:i)")
        ->execute([':u'=>$u,':i'=>$i]);
  }
};

if ($action === 'add') {
  $ck = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=:u AND item_id=:i");
  $ck->execute([':u'=>$uid, ':i'=>$itemId]);
  if (!$ck->fetch()) $insertFav($uid,$itemId);
  json_ok(['status'=>'added']);
}

if ($action === 'remove') {
  $pdo->prepare("DELETE FROM favorites WHERE user_id=:u AND item_id=:i")
      ->execute([':u'=>$uid,':i'=>$itemId]);
  json_ok(['status'=>'removed']);
}

// toggle
$ck = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id=:u AND item_id=:i");
$ck->execute([':u'=>$uid, ':i'=>$itemId]);
if ($ck->fetch()) {
  $pdo->prepare("DELETE FROM favorites WHERE user_id=:u AND item_id=:i")
      ->execute([':u'=>$uid,':i'=>$itemId]);
  json_ok(['status'=>'removed']);
} else {
  $insertFav($uid,$itemId);
  json_ok(['status'=>'added']);
}
