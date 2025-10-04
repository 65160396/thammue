<?php
// /page/backend/exchange/exchange_item_locations.php
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD']==='GET'){
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0){ http_response_code(400); exit('bad id'); }
  $q = $conn->prepare("SELECT province,district,subdistrict,postcode,address_line FROM exchange_items WHERE id=? LIMIT 1");
  $q->bind_param('i',$id);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true,'location'=>$row], JSON_UNESCAPED_UNICODE);
  exit;
}

http_response_code(405);
echo 'Method Not Allowed';
