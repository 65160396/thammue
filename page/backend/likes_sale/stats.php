<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4","root","",[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

$type   = $_GET['type'] ?? 'product';
if(!in_array($type,['product','exchange'],true)) $type='product';
$id     = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if($id <= 0){ http_response_code(400); echo json_encode(['error'=>'bad id']); exit; }

// จำนวนคนถูกใจ
$st = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE item_type=? AND item_id=?");
$st->execute([$type,$id]);
$count = (int)$st->fetchColumn();

// ฉันถูกใจอยู่ไหม
$liked = false;
if($userId){
  $st = $pdo->prepare("SELECT 1 FROM favorites WHERE item_type=? AND item_id=? AND user_id=?");
  $st->execute([$type,$id,$userId]);
  $liked = (bool)$st->fetchColumn();
}

// (ออปชัน) นับขายแล้ว
$sold = null;
if($type==='product'){
  $st = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN status='paid' THEN qty ELSE 0 END),0)
                       FROM order_items WHERE product_id=?");
  $st->execute([$id]);
  $sold = (int)$st->fetchColumn();
}

echo json_encode(['count'=>$count,'liked'=>$liked,'sold_count'=>$sold], JSON_UNESCAPED_UNICODE);
