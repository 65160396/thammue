<?php
// /thammue/api/requests/detail_for_modal.php
declare(strict_types=1);

require __DIR__ . '/../_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  json_err('METHOD_NOT_ALLOWED', 405);
}

$pdo = db();
$uid = me_id();
if (!$uid) json_err('AUTH', 401);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_err('BAD_REQ', 400);

// ดึงคำขอ + ตรวจสิทธิ์เจ้าของ
$st = $pdo->prepare("
  SELECT r.id, r.item_id, r.requester_user_id, r.requester_item_id,
         r.message, r.status,
         i.user_id AS owner_id,
         ri.title  AS requester_item_title
  FROM requests r
  JOIN items   i  ON i.id = r.item_id
  LEFT JOIN items ri ON ri.id = r.requester_item_id
  WHERE r.id = :id
  LIMIT 1
");
$st->execute([':id'=>$id]);
$req = $st->fetch();
if (!$req) json_err('NOT_FOUND', 404);
if ((int)$req['owner_id'] !== $uid) json_err('FORBIDDEN', 403);

$requesterId  = (int)$req['requester_user_id'];
$preferItemId = (int)($req['requester_item_id'] ?: 0) ?: null;

// ---- helper: ตรวจว่าตารางมีคอลัมน์ไหม ----
$hasCols = static function(string $table, array $cols) use ($pdo): bool {
  try {
    $in  = implode(',', array_fill(0, count($cols), '?'));
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ($in)";
    $st  = $pdo->prepare($sql);
    $st->execute(array_merge([$table], $cols));
    return ((int)$st->fetchColumn()) === count($cols);
  } catch (Throwable $e) { return false; }
};

// ---- 1) items (requester_item) ก่อน ----
$loc = ['province'=>'-','district'=>'-','subdistrict'=>'-','place_detail'=>null];
if ($preferItemId && $hasCols('items', ['province','district','subdistrict','place_detail'])) {
  $q = $pdo->prepare("SELECT province, district, subdistrict, place_detail FROM items WHERE id=:i LIMIT 1");
  $q->execute([':i'=>$preferItemId]);
  if ($row = $q->fetch()) {
    foreach (['province','district','subdistrict','place_detail'] as $k) {
      if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) $loc[$k] = $row[$k];
    }
  }
}

// ---- 2) users → user_profiles (ถ้ายังว่าง) ----
if ($loc['province']==='-' && $loc['district']==='-' && $loc['subdistrict']==='-') {
  if ($hasCols('users', ['province']) &&
      ($hasCols('users', ['district','subdistrict']) || $hasCols('users', ['amphoe','tambon']))) {

    $cols = $hasCols('users', ['district','subdistrict'])
      ? 'district, subdistrict'
      : 'amphoe as district, tambon as subdistrict';

    $q = $pdo->prepare("SELECT province, {$cols} FROM users WHERE id=:u LIMIT 1");
    $q->execute([':u'=>$requesterId]);
    if ($row = $q->fetch()) {
      $loc['province']    = $row['province']    ?? $loc['province'];
      $loc['district']    = $row['district']    ?? $loc['district'];
      $loc['subdistrict'] = $row['subdistrict'] ?? $loc['subdistrict'];
    }
  }

  if ($loc['province']==='-' && $loc['district']==='-' && $loc['subdistrict']==='-'
      && $hasCols('user_profiles', ['province']) &&
         ($hasCols('user_profiles', ['district','subdistrict']) || $hasCols('user_profiles', ['amphoe','tambon']))) {

    $cols = $hasCols('user_profiles', ['district','subdistrict'])
      ? 'district, subdistrict'
      : 'amphoe as district, tambon as subdistrict';

    $q = $pdo->prepare("SELECT province, {$cols} FROM user_profiles WHERE user_id=:u LIMIT 1");
    $q->execute([':u'=>$requesterId]);
    if ($row = $q->fetch()) {
      $loc['province']    = $row['province']    ?? $loc['province'];
      $loc['district']    = $row['district']    ?? $loc['district'];
      $loc['subdistrict'] = $row['subdistrict'] ?? $loc['subdistrict'];
    }
  }
}

// ---- 3) items อื่นของผู้ขอ (fallback สุดท้าย) ----
if ($loc['province']==='-' && $loc['district']==='-' && $loc['subdistrict']==='-'
    && $hasCols('items', ['province','district','subdistrict','place_detail'])) {
  $q = $pdo->prepare("SELECT province, district, subdistrict, place_detail
                      FROM items WHERE user_id=:u ORDER BY id DESC LIMIT 1");
  $q->execute([':u'=>$requesterId]);
  if ($row = $q->fetch()) {
    foreach (['province','district','subdistrict','place_detail'] as $k) {
      if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) $loc[$k] = $row[$k];
    }
  }
}

json_ok([
  'id' => (int)$req['id'],
  'requester_item_title' => (string)($req['requester_item_title'] ?? '-'),
  'requester_note'       => (string)($req['message'] ?? ''),
  'requester_location'   => [
    'province'    => $loc['province'],
    'district'    => $loc['district'],
    'subdistrict' => $loc['subdistrict'],
    'place_detail'=> $loc['place_detail'],
  ],
]);
