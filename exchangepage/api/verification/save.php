<?php
// /exchangepage/api/verification/save.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = db();
  $uid = require_login();

  $need = ['full_name','dob_iso','citizen_id','province','district','subdistrict','postcode','addr_line'];
  foreach ($need as $k) {
    if (!isset($_POST[$k]) || trim((string)$_POST[$k]) === '') json_err("missing_$k", 422);
  }
  if (empty($_FILES['id_front']) || ($_FILES['id_front']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_err('missing_id_front', 422);
  }

  // helper: เซฟไฟล์เดียวไปที่ /exchangepage/public/uploads/ids
  $save_one = function(array $file, array $allowExt, int $maxBytes=5_000_000): string {
    if ($file['error'] !== UPLOAD_ERR_OK) json_err('upload_error', 400);
    if ($file['size'] > $maxBytes)        json_err('file_too_large', 413);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowExt, true)) json_err('bad_file_type', 415);

    $baseDir = UPLOAD_DIR . '/ids';
    if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $baseDir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) json_err('save_failed', 500);

    // เก็บเป็น path relative สำหรับเสิร์ฟผ่าน public
    return 'uploads/ids/' . $name;
  };

  $relPath = $save_one($_FILES['id_front'], ['jpg','jpeg','png','webp','gif','pdf']);

  $full_name = trim((string)$_POST['full_name']);
  $dob       = trim((string)$_POST['dob_iso']); // YYYY-MM-DD
  $cid_raw   = preg_replace('/\D/', '', (string)$_POST['citizen_id']);
  $cid_mask  = substr($cid_raw, 0, 6) . str_repeat('•', max(0, 13 - 6));

  $sql = "INSERT INTO verifications
          (user_id, full_name, dob, citizen_id, province, district, subdistrict, postcode, addr_line, id_front_path, status, created_at)
          VALUES
          (:u, :name, :dob, :cid, :pv, :dt, :sd, :pc, :addr, :file, 'verified', NOW())";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':u'    => $uid,
    ':name' => $full_name,
    ':dob'  => $dob,
    ':cid'  => $cid_mask,
    ':pv'   => trim((string)$_POST['province']),
    ':dt'   => trim((string)$_POST['district']),
    ':sd'   => trim((string)$_POST['subdistrict']),
    ':pc'   => trim((string)$_POST['postcode']),
    ':addr' => trim((string)$_POST['addr_line']),
    ':file' => $relPath,
  ]);

  json_ok();
} catch (Throwable $e) {
  json_err('server_error', 500, ['msg'=>$e->getMessage()]);
}
