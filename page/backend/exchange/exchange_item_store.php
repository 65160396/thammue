<?php
// exchange_item_store.php
session_start();
require_once __DIR__ . '/../config.php'; // แก้ path ให้ชี้ถึง config ที่สร้าง $conn (mysqli)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ------------ helpers ------------ */
function must($cond, $msg)
{
  if (!$cond) {
    throw new RuntimeException($msg);
  }
}

function saveImageFile(array $file, string $dir, string $basename)
{
  if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
  must($file['error'] === UPLOAD_ERR_OK, 'อัปโหลดไฟล์ผิดพลาด');

  must($file['size'] <= 5 * 1024 * 1024, 'ไฟล์เกิน 5MB');

  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  $ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    default      => null
  };
  must($ext !== null, 'รองรับเฉพาะ JPG/PNG');

  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  $name = $basename . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $to   = rtrim($dir, '/') . '/' . $name;
  move_uploaded_file($file['tmp_name'], $to);

  // คืน path แบบ “เว็บ” (relative จาก document root)
  return str_replace($_SERVER['DOCUMENT_ROOT'], '', $to);
}

function saveImagesFromField(string $field, string $baseDir, int $item_id, mysqli $conn, string $kind)
{
  if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return 0;

  $count = min(count($_FILES[$field]['name']), 8);
  $saved = 0;
  for ($i = 0; $i < $count; $i++) {
    $file = [
      'name'     => $_FILES[$field]['name'][$i],
      'type'     => $_FILES[$field]['type'][$i],
      'tmp_name' => $_FILES[$field]['tmp_name'][$i],
      'error'    => $_FILES[$field]['error'][$i],
      'size'     => $_FILES[$field]['size'][$i],
    ];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

    $dir  = $_SERVER['DOCUMENT_ROOT'] . $baseDir;
    $path = saveImageFile($file, $dir, $kind . $i);
    if ($path) {
      $sort = $i;
      $st = $conn->prepare("INSERT INTO exchange_item_images (item_id,file_path,sort_order,kind) VALUES (?,?,?,?)");
      $st->bind_param('isis', $item_id, $path, $sort, $kind);
      $st->execute();
      $st->close();
      $saved++;
    }
  }
  return $saved;
}

/* ------------ main ------------ */
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
  }

  // dev: ถ้าไม่มี session ให้จำลอง user_id = 1
  if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
  }
  $user_id = (int)$_SESSION['user_id'];

  // ----- รับค่าจากฟอร์ม (ใช้ name ตามหน้าใหม่) -----
  $title        = trim($_POST['title'] ?? '');
  $category_id  = (int)($_POST['category_id'] ?? 0);
  $description  = trim($_POST['description'] ?? '');

  // สเต็ป 2 (สิ่งที่ต้องการ) – เก็บในช่อง wanted เป็น JSON เพื่อไม่ต้องแก้ schema
  $want_title       = trim($_POST['want_title'] ?? '');
  $want_category_id = (int)($_POST['want_category_id'] ?? 0) ?: null;
  $want_note        = trim($_POST['want_note'] ?? '');
  $wanted_json      = json_encode([
    'title'       => $want_title,
    'category_id' => $want_category_id,
    'note'        => $want_note
  ], JSON_UNESCAPED_UNICODE);

  // สเต็ป 3 (สถานที่นัดรับ) – map ชื่อใหม่ให้ตรงคอลัมน์เดิม
  $province    = trim($_POST['province'] ?? '');
  $district    = trim($_POST['district'] ?? '');
  $subdistrict = trim($_POST['subdistrict'] ?? '');
  $postcode    = trim($_POST['zipcode'] ?? '');        // <— ชื่อใหม่ zipcode
  $addr_line   = trim($_POST['place_detail'] ?? '');   // <— ชื่อใหม่ place_detail

  must($title !== '' && $category_id > 0, 'กรุณากรอกชื่อสินค้าและหมวดหมู่');

  $conn->begin_transaction();

  // ----- สร้างรายการสินค้าแลก -----
  $st = $conn->prepare("
    INSERT INTO exchange_items
      (user_id,title,category_id,description,wanted,
       province,district,subdistrict,postcode,address_line,status,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, 'active', NOW())
  ");
  $st->bind_param(
    'isisssssss',
    $user_id,
    $title,
    $category_id,
    $description,
    $wanted_json,
    $province,
    $district,
    $subdistrict,
    $postcode,
    $addr_line
  );
  $st->execute();
  $item_id = $st->insert_id;
  $st->close();

  // ----- บันทึกรูปภาพ -----
  // รูปสินค้าจริง: images[]
  saveImagesFromField('images', "/uploads/exchange/{$item_id}/item", $item_id, $conn, 'item');

  // รูปอ้างอิงสิ่งที่อยากได้ (ถ้ามี): want_images[]
  saveImagesFromField('want_images', "/uploads/exchange/{$item_id}/want", $item_id, $conn, 'want');

  $conn->commit();

  // กลับไปหน้าเดิมพร้อม ok
  header('Location: /exchangepage/Uplode.html?ok=1');
  exit;
} catch (Throwable $e) {
  if ($conn && $conn->errno === 0) { /* not mysqli error */
  }
  if ($conn) {
    $conn->rollback();
  }
  http_response_code(400);
  echo "❌ " . $e->getMessage();
}
