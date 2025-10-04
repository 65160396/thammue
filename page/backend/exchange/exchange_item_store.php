<?php
// /page/backend/exchange/exchange_item_store.php
session_start();
require_once __DIR__ . '/../config.php'; // $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

function must($cond, $msg){
  if (!$cond) { throw new RuntimeException($msg); }
}

function saveImage($file, $dir, $basename){
  if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
  must($file['error'] === UPLOAD_ERR_OK, 'อัปโหลดไฟล์ผิดพลาด');
  must($file['size'] <= 5*1024*1024, 'ไฟล์ภาพเกิน 5MB');

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);
  $ext = match($mime){
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    default      => null
  };
  must($ext !== null, 'รองรับเฉพาะ JPG/PNG');

  if (!is_dir($dir)) { mkdir($dir, 0775, true); }
  $name = $basename . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $to   = rtrim($dir,'/') . '/' . $name;
  move_uploaded_file($file['tmp_name'], $to);
  return $to;
}

try {
  // ------- รับค่าจากฟอร์ม -------
  $title       = trim($_POST['title'] ?? '');
  $category_id = (int)($_POST['category_id'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $wanted      = trim($_POST['wanted'] ?? '');

  $province    = trim($_POST['province'] ?? '');
  $district    = trim($_POST['district'] ?? '');
  $subdistrict = trim($_POST['subdistrict'] ?? '');
  $postcode    = trim($_POST['postcode'] ?? '');
  $addr_line   = trim($_POST['address_line'] ?? '');

  must($title !== '', 'กรุณากรอกชื่อสินค้า');
  must($category_id > 0, 'กรุณาเลือกหมวดหมู่');

  // ------- หา user_id -------
  if (empty($_SESSION['user_id'])) {
    // (โหมด dev) – ในจริงจัง ให้ redirect ไปหน้า login
    $_SESSION['user_id'] = 1;
  }
  $user_id = (int)$_SESSION['user_id'];

  $conn->begin_transaction();

  // ------- insert item -------
  $stmt = $conn->prepare("
    INSERT INTO exchange_items
      (user_id,title,category_id,description,wanted,
       province,district,subdistrict,postcode,address_line,status,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, 'active', NOW())
  ");
  $stmt->bind_param(
    'isisssssss',
    $user_id,$title,$category_id,$description,$wanted,
    $province,$district,$subdistrict,$postcode,$addr_line
  );
  $stmt->execute();
  $item_id = $stmt->insert_id;
  $stmt->close();

  // ------- บันทึกรูป (photos[] multiple) -------
  $savedCount = 0;
  if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $max = min(count($_FILES['photos']['name']), 8); // จำกัด 8 รูป
    for ($i=0; $i<$max; $i++){
      $file = [
        'name'     => $_FILES['photos']['name'][$i],
        'type'     => $_FILES['photos']['type'][$i],
        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
        'error'    => $_FILES['photos']['error'][$i],
        'size'     => $_FILES['photos']['size'][$i],
      ];
      if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

      $fullpath = saveImage($file, $_SERVER['DOCUMENT_ROOT']."/uploads/exchange/{$item_id}", "img{$i}");
      if ($fullpath){
        // เก็บเป็น path แบบเว็บ
        $webPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $fullpath);
        $insImg = $conn->prepare("INSERT INTO exchange_item_images (item_id,file_path,sort_order) VALUES (?,?,?)");
        $sort = $i;
        $insImg->bind_param('isi', $item_id, $webPath, $sort);
        $insImg->execute();
        $insImg->close();
        $savedCount++;
      }
    }
  }

  $conn->commit();

  // สำเร็จ → กลับหน้าอัปโหลดหรือไปหน้ารายละเอียด
  header('Location: /exchangepage/Uplode.html?ok=1');
  exit;

} catch (Throwable $e) {
  if ($conn->errno === 0) { /* not from mysqli */ }
  if ($conn->errno || $conn->sqlstate) { $conn->rollback(); }
  http_response_code(400);
  echo "❌ " . $e->getMessage();
}
