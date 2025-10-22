<?php
// /exchangepage/api/requests/accept_with_meeting.php
declare(strict_types=1);

require __DIR__ . '/../_config.php';
require __DIR__ . '/../notifications/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$pdo = db();
$uid = me_id(); if (!$uid) json_err('AUTH', 401);

$id         = (int)($_POST['id'] ?? 0);
$meetingRaw = trim((string)($_POST['meeting_at'] ?? ''));
$myNote     = trim((string)($_POST['my_note'] ?? ''));

if ($id <= 0)           json_err('BAD_REQ', 400);
if ($meetingRaw === '') json_err('MEETING_REQUIRED', 422);

$meetingAt = str_replace('T', ' ', $meetingRaw);
if (!preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $meetingAt)) {
  json_err('BAD_DATETIME', 422, ['hint' => 'use <input type="datetime-local"> value']);
}

$pdo->beginTransaction();
try {
  $st = $pdo->prepare("
    SELECT r.id, r.item_id, r.requester_user_id, r.requester_item_id,
           r.message, r.status, i.user_id AS owner_id, i.title AS item_title,
           ou.display_name AS owner_name
    FROM requests r
    JOIN items   i  ON i.id = r.item_id
    JOIN users   ou ON ou.id = i.user_id
    WHERE r.id = :id
    LIMIT 1
  ");
  $st->execute([':id'=>$id]);
  $r = $st->fetch();
  if (!$r)                          throw new RuntimeException('NOT_FOUND');
  if ((int)$r['owner_id'] !== $uid) throw new RuntimeException('FORBIDDEN');

  if ($r['status'] !== 'pending') {
    $pdo->commit();
    json_ok(['status'=>$r['status']]);
  }

  $ownerLoc = get_best_location($pdo, (int)$r['owner_id'],          (int)$r['item_id'] ?: null);
  $reqLoc   = get_best_location($pdo, (int)$r['requester_user_id'], (int)($r['requester_item_id'] ?: 0) ?: null);

  $pdo->prepare("UPDATE requests SET status='accepted', decided_at=NOW() WHERE id=:id")
      ->execute([':id'=>$id]);

  $targetUserId = (int)$r['requester_user_id'];
  $itemKey      = (int)($r['requester_item_id'] ?: $r['item_id']);
  $a = min($uid, $targetUserId); $b = max($uid, $targetUserId);

  $sel = $pdo->prepare("SELECT id FROM conversations WHERE pair_a=:a AND pair_b=:b ORDER BY last_msg_at DESC, id DESC LIMIT 1");
  $sel->execute([':a'=>$a, ':b'=>$b]);
  $convId = (int)($sel->fetchColumn() ?: 0);

  if ($convId <= 0) {
    $ins = $pdo->prepare("INSERT INTO conversations
      (item_id,user_a,user_b,pair_a,pair_b,item_key,created_at,last_msg_at)
      VALUES (:item,:u1,:u2,:a,:b,:k,NOW(),NOW())");
    $ins->execute([':item'=>$itemKey, ':u1'=>$uid, ':u2'=>$targetUserId, ':a'=>$a, ':b'=>$b, ':k'=>$itemKey]);
    $convId = (int)$pdo->lastInsertId();
  }

  $fmt = static fn($v)=>($v!==null && $v!=='') ? $v : '-';
  $ownLocStr = 'จ.'.$fmt($ownerLoc['province']).' อ.'.$fmt($ownerLoc['district']).' ต.'.$fmt($ownerLoc['subdistrict']);
  $reqLocStr = 'จ.'.$fmt($reqLoc['province']).' อ.'.$fmt($reqLoc['district']).' ต.'.$fmt($reqLoc['subdistrict']);

  $ownDetail = implode('; ', array_values(array_filter([
    $ownerLoc['place_detail'] ?: null,
    ($myNote !== '' ? "โน้ต: {$myNote}" : null),
  ])));

  $noteFromReq = trim((string)$r['message']) !== '' ? $r['message'] : '';
  $reqDetail = implode('; ', array_values(array_filter([
    $reqLoc['place_detail'] ?: null,
    ($noteFromReq !== '' ? "โน้ต: {$noteFromReq}" : null),
  ])));

  $myName        = $r['owner_name'] ?: 'ฉัน';
  $myItemTitle   = (string)$r['item_title'];
  $theirItemTitle= '';
  if (!empty($r['requester_item_id'])) {
    $t = $pdo->prepare("SELECT title FROM items WHERE id=:i LIMIT 1");
    $t->execute([':i'=>(int)$r['requester_item_id']]);
    $theirItemTitle = (string)($t->fetchColumn() ?: '');
  }

  $lines = [];
  $lines[] = "{$myName} ตอบรับคำขอของคุณ";
  $lines[] = "วัน-เวลานัดหมาย: {$meetingAt}";
  $lines[] = "สถานที่ของฉัน: {$ownLocStr}" . ($ownDetail ? " ({$ownDetail})" : "");
  $lines[] = "สถานที่ของคุณ: {$reqLocStr}" . ($reqDetail ? " ({$reqDetail})" : "");
  $lines[] = "สินค้าเรา: " . ($myItemTitle !== '' ? $myItemTitle : '-') . " (ID: {$r['item_id']})";
  $lines[] = "สินค้าคุณ: " . ($theirItemTitle !== '' ? $theirItemTitle : '-') .
             " (ID: " . ((int)$r['requester_item_id'] ?: 0) . ")";
  $autoBody = implode("\n", $lines);

  $pdo->prepare("INSERT INTO messages (conv_id, sender_id, body, created_at) VALUES (:c,:u,:b,NOW())")
      ->execute([':c'=>$convId, ':u'=>$uid, ':b'=>$autoBody]);
  $pdo->prepare("UPDATE conversations SET last_msg_at=NOW() WHERE id=:c")->execute([':c'=>$convId]);

  $title = "คำขอแลกของคุณถูกตอบรับ";
  $body  = ($myItemTitle ? "รายการ: \"{$myItemTitle}\" (คำขอ #{$id})" : "คำขอ #{$id}");
  $link  = THAMMUE_BASE . "/public/chat.html?c={$convId}";
  notify($pdo, $targetUserId, 'exchange', $title, $body, $link);

  $pdo->commit();

  json_ok([
    'status'     => 'accepted',
    'conv_id'    => $convId,
    'chat_url'   => THAMMUE_BASE . "/public/chat.html?c={$convId}",
    'meeting_at' => $meetingAt
  ]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_err('UPDATE_FAIL', 500, ['err'=>$e->getMessage()]);
}

/* helpers: เหมือนเดิม */
function get_best_location(PDO $pdo, int $userId, ?int $preferItemId = null): array {
  $out = ['province'=>'-','district'=>'-','subdistrict'=>'-','place_detail'=>null];

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

  if ($preferItemId && $hasCols('items', ['province','district','subdistrict','place_detail'])) {
    $q = $pdo->prepare("SELECT province, district, subdistrict, place_detail FROM items WHERE id=:i LIMIT 1");
    $q->execute([':i'=>$preferItemId]);
    if ($row = $q->fetch()) foreach (['province','district','subdistrict','place_detail'] as $k)
      if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) $out[$k] = $row[$k];
  }
  if ($out['province']!=='-' || $out['district']!=='-' || $out['subdistrict']!=='-') return $out;

  if ($hasCols('users', ['province']) &&
     ($hasCols('users', ['district','subdistrict']) || $hasCols('users', ['amphoe','tambon']))) {
    $cols = $hasCols('users', ['district','subdistrict']) ? 'district, subdistrict' : 'amphoe as district, tambon as subdistrict';
    $q = $pdo->prepare("SELECT province, {$cols} FROM users WHERE id=:u LIMIT 1");
    $q->execute([':u'=>$userId]);
    if ($row = $q->fetch()) {
      $out['province']=$row['province']??$out['province'];
      $out['district']=$row['district']??$out['district'];
      $out['subdistrict']=$row['subdistrict']??$out['subdistrict'];
    }
  }
  if ($out['province']!=='-' || $out['district']!=='-' || $out['subdistrict']!=='-') return $out;

  if ($hasCols('user_profiles', ['province']) &&
     ($hasCols('user_profiles', ['district','subdistrict']) || $hasCols('user_profiles', ['amphoe','tambon']))) {
    $cols = $hasCols('user_profiles', ['district','subdistrict']) ? 'district, subdistrict' : 'amphoe as district, tambon as subdistrict';
    $q = $pdo->prepare("SELECT province, {$cols} FROM user_profiles WHERE user_id=:u LIMIT 1");
    $q->execute([':u'=>$userId]);
    if ($row = $q->fetch()) {
      $out['province']=$row['province']??$out['province'];
      $out['district']=$row['district']??$out['district'];
      $out['subdistrict']=$row['subdistrict']??$out['subdistrict'];
    }
  }
  if ($out['province']!=='-' || $out['district']!=='-' || $out['subdistrict']!=='-') return $out;

  if ($hasCols('items', ['province','district','subdistrict','place_detail'])) {
    $q = $pdo->prepare("SELECT province, district, subdistrict, place_detail
                        FROM items WHERE user_id=:u ORDER BY id DESC LIMIT 1");
    $q->execute([':u'=>$userId]);
    if ($row = $q->fetch()) foreach (['province','district','subdistrict','place_detail'] as $k)
      if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) $out[$k] = $row[$k];
  }
  return $out;
}
