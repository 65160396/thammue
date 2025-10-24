<?php
// /page/backend/ex_request_decide.php
require_once __DIR__ . '/ex__common.php';
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- helpers ---------- */
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
  $st = $db->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  return (bool)($st->get_result()->fetch_row()[0] ?? 0);
}

/**
 * ล็อก item ตาม schema ที่มีอยู่จริง
 * จะพยายามอัปเดตตามลำดับ:
 *   - status='locked' (ถ้ามีคอลัมน์ status)
 *   - is_locked=1     (ถ้ามีคอลัมน์ is_locked)
 *   - available=0     (ถ้ามีคอลัมน์ available)
 *   - locked_at=NOW() (ถ้ามีคอลัมน์ locked_at)
 * คืนค่า true ถ้าล็อกได้อย่างน้อย 1 ฟิลด์, false ถ้าไม่พบคอลัมน์ให้ล็อก
 */
function lock_items_if_possible(mysqli $m, array $ids): bool {
  if (!$ids) return false;
  $hasStatus   = col_exists($m,'ex_items','status');
  $hasIsLocked = col_exists($m,'ex_items','is_locked');
  $hasAvail    = col_exists($m,'ex_items','available');
  $hasLockedAt = col_exists($m,'ex_items','locked_at');

  $sets = [];
  $types = '';
  $vals = [];

  if ($hasStatus)   { $sets[] = "status=?";    $types.='s'; $vals[]='locked'; }
  if ($hasIsLocked) { $sets[] = "is_locked=?"; $types.='i'; $vals[]=1; }
  if ($hasAvail)    { $sets[] = "available=?"; $types.='i'; $vals[]=0; }
  if ($hasLockedAt) { $sets[] = "locked_at=NOW()"; }

  if (!$sets) return false; // ไม่มีคอลัมน์ให้ล็อก

  // สร้าง IN (?, ?, ?)
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types .= str_repeat('i', count($ids));
  $vals = array_merge($vals, $ids);

  $sql = "UPDATE ex_items SET ".implode(', ', $sets)." WHERE id IN ($placeholders)";
  $st  = $m->prepare($sql);
  $st->bind_param($types, ...$vals);
  $st->execute();

  return true;
}

try {
  $m = dbx();
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $uid = me();
  if (!$uid) { echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit; }

  $rid    = (int)($_POST['request_id'] ?? 0);
  $action = trim($_POST['action'] ?? '');

  // รับจากฟอร์มโมดัล
  $meet_date = trim($_POST['meet_date'] ?? '');
  $meet_time = trim($_POST['meet_time'] ?? '');
  $place     = trim($_POST['place']     ?? '');
  $note      = trim($_POST['note']      ?? '');

  // normalize
  $meet_date = $meet_date !== '' ? $meet_date : null;   // YYYY-MM-DD
  $meet_time = $meet_time !== '' ? $meet_time : null;   // HH:MM
  $place     = $place     !== '' ? $place     : null;
  $note      = $note      !== '' ? $note      : null;

  if ($rid <= 0 || !in_array($action, ['accept','decline'], true)) {
    echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
  }

  $m->begin_transaction();

  // อ่านคำขอ + ตรวจสิทธิ์ + เอา item id มาด้วยเพื่อไปล็อก
  $st = $m->prepare("
    SELECT id, status, owner_user_id, requester_user_id,
           requested_item_id, offered_item_id
    FROM ex_requests
    WHERE id=? FOR UPDATE
  ");
  $st->bind_param('i', $rid);
  $st->execute();
  $req = $st->get_result()->fetch_assoc();
  if (!$req) { $m->rollback(); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  $owner     = (int)$req['owner_user_id'];
  $requester = (int)$req['requester_user_id'];
  $itemReqId = (int)$req['requested_item_id'];
  $itemOffId = (int)$req['offered_item_id'];

  // ให้เจ้าของ (owner) เป็นคนยอมรับ/ปฏิเสธ
  if ($uid !== $owner) { $m->rollback(); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

  if ($action === 'decline') {
    $st = $m->prepare("UPDATE ex_requests SET status='declined', updated_at=NOW() WHERE id=? AND owner_user_id=?");
    $st->bind_param('ii', $rid, $owner);
    $st->execute();
    $m->commit();
    echo json_encode(['ok'=>true,'request_id'=>$rid]); exit;
  }

  /* ========== accept ========== */
  // ยืดหยุ่นตามคอลัมน์ที่มีจริง
  $has = function(mysqli $db, string $col): bool {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='ex_requests' AND COLUMN_NAME=?";
    $s = $db->prepare($sql); $s->bind_param('s',$col); $s->execute();
    return (bool)($s->get_result()->fetch_row()[0] ?? 0);
  };

  $set = "status='accepted', updated_at=NOW()";
  $params = []; $types = '';

  if ($has($m,'meet_date'))  { $set .= ", meet_date=?";  $params[]=$meet_date;  $types.='s'; }
  if ($has($m,'meet_time'))  { $set .= ", meet_time=?";  $params[]=$meet_time;  $types.='s'; }
  if ($has($m,'meet_place')) { $set .= ", meet_place=?"; $params[]=$place;      $types.='s'; }
  if ($has($m,'meet_note'))  { $set .= ", meet_note=?";  $params[]=$note;       $types.='s'; }

  $meet_dt = ($meet_date && $meet_time) ? ($meet_date.' '.$meet_time.':00') : null;
  if ($meet_dt && $has($m,'meet_at'))     { $set .= ", meet_at=?";     $params[]=$meet_dt; $types.='s'; }
  if ($meet_dt && $has($m,'meeting_at'))  { $set .= ", meeting_at=?";  $params[]=$meet_dt; $types.='s'; }
  if ($note    && $has($m,'meeting_note')){ $set .= ", meeting_note=?";$params[]=$note;    $types.='s'; }

  $sql = "UPDATE ex_requests SET $set WHERE id=? AND owner_user_id=?";
  $params[]=$rid;   $types.='i';
  $params[]=$owner; $types.='i';
  $st = $m->prepare($sql);
  $st->bind_param($types, ...$params);
  $st->execute();

  // sync เข้า ex_meetings
  $st = $m->prepare("
    INSERT INTO ex_meetings
      (request_id, owner_user_id, requester_user_id, meet_date, meet_time, meet_place, meet_note, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      meet_date=VALUES(meet_date),
      meet_time=VALUES(meet_time),
      meet_place=VALUES(meet_place),
      meet_note=VALUES(meet_note),
      updated_at=NOW()
  ");
  $md = $meet_date ?: NULL; $mt = $meet_time ?: NULL; $mp = $place ?: NULL; $mn = $note ?: NULL;
  $st->bind_param("iiissss", $rid, $owner, $requester, $md, $mt, $mp, $mn);
  $st->execute();

  // ---------- ล็อกสินค้า (เฉพาะตอน accept) ----------
  $lockOk = lock_items_if_possible($m, array_filter([$itemReqId, $itemOffId]));

  $m->commit();
  echo json_encode(['ok'=>true,'request_id'=>$rid, 'lock_warn'=>($lockOk?false:true)]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'fatal: '.$e->getMessage()]);
}
