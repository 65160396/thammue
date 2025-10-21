<?php
// /thammue/api/verification/save.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');

// เปิดโหมด DEV verbose ชั่วคราว
const DEV_VERBOSE = true;

try {
  $pdo = db();
  $uid = require_login(); // ถ้าไม่ล็อกอินจะโยน json_err ทันที

  // --- ตรวจฟิลด์ที่ต้องมีตามฟอร์ม modal ---
  $need = ['full_name','dob_iso','citizen_id','province','district','subdistrict','postcode','addr_line'];
  foreach ($need as $k) {
    if (!isset($_POST[$k]) || trim((string)$_POST[$k]) === '') {
      json_err("missing_$k");
    }
  }
  if (empty($_FILES['id_front']) || ($_FILES['id_front']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_err('missing_id_front');
  }

  // --- เซฟไฟล์บัตร (อนุญาตภาพ/PDF) ---
  // โฟลเดอร์จะถูกสร้างอัตโนมัติ ถ้าไม่มี
  $relPath = save_upload($_FILES['id_front'], 'uploads/ids', ['jpg','jpeg','png','webp','gif','pdf'], 5_000_000);

  // --- เตรียมค่าบันทึกเข้าตาราง verifications ของคุณ ---
  // ตารางของคุณ: id, user_id, full_name, dob, citizen_id, province, district, subdistrict, postcode, addr_line, id_front_path, status, created_at
  $full_name = trim((string)$_POST['full_name']);
  $dob       = trim((string)$_POST['dob_iso']); // YYYY-MM-DD
  // เก็บเลขบัตรแบบ mask
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

  // (ไม่ต้องมีหน้าแอดมินก็ได้) — ส่ง ok กลับเลย ให้ใช้งานต่อได้
  json_ok();

} catch (Throwable $e) {
  if (DEV) {
    // โยนรายละเอียดเพื่อ debug ชั่วคราว
    json_err('server_error', 500, ['msg' => $e->getMessage()]);
  } else {
    json_err('server_error', 500);
  }
}
