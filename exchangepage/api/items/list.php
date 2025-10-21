<?php
// /thammue/api/items/list.php
// รายการสินค้าสำหรับหน้า index/list
// - ส่ง user_id และ is_owner ให้ FE รู้ว่าเป็นเจ้าของหรือไม่        // [ADD]
// - ทำให้ URL รูป (cover) แข็งแรงด้วย pub_url() ไม่ว่า DB จะเก็บแบบไหน // [ADD]

declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$pdo = db();

/**
 * แปลง path จาก DB ให้เป็น URL ที่เสิร์ฟได้จริงอย่างปลอดภัย
 * รองรับหลายกรณี: http(s)://*, /absolute/path, หรือ relative 'uploads/...'
 */
function pub_url(?string $p): ?string {                                   // [ADD]
  if (!$p) return null;
  $p = trim($p);
  if ($p === '') return null;
  if (preg_match('#^https?://#i', $p)) return $p; // เป็น URL เต็มอยู่แล้ว
  if ($p[0] === '/') return $p;                   // เป็น absolute path แล้ว
  // กรณีเป็น relative path ในโปรเจกต์ เช่น 'uploads/items/...'
  return '/thammue/' . ltrim($p, '/');
}

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 12)));
$offset = ($page - 1) * $limit;
$catId  = (int)($_GET['category_id'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));
$mine   = (int)($_GET['mine'] ?? 0);

// ดึง user ปัจจุบันจาก session (ถ้ามี me_id() ก็ลองใช้ก่อน)           // [ADD]
$cuid = function_exists('me_id') ? (int)(me_id() ?? 0) : (int)($_SESSION['user_id'] ?? 0);

$where  = [];
$params = [];

if ($mine === 1) {
  if (!$cuid) json_err('unauthorized', 401);
  $where[] = 'i.user_id = :uid';
  $params[':uid'] = $cuid;
} else {
  $where[] = "i.visibility='public'";
}

if ($catId > 0) {
  $where[] = "i.category_id = :cid";
  $params[':cid'] = $catId;
}
if ($q !== '')  {
  $where[] = "(i.title LIKE :q OR i.description LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// นับทั้งหมด
$cnt = $pdo->prepare("SELECT COUNT(*) FROM items i {$whereSql}");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

// ดึงรายการ                                                          // [CHANGE]
$sql = "
  SELECT
    i.id,
    i.title,
    i.category_id,
    i.province,
    i.user_id,                                           -- ส่งออก user_id   [ADD]
    (SELECT path FROM item_images im
      WHERE im.item_id=i.id
      ORDER BY sort_order,id LIMIT 1) AS cover,
    (CASE WHEN i.user_id = :cuid THEN 1 ELSE 0 END) AS is_owner  -- flag เจ้าของ [ADD]
  FROM items i
  {$whereSql}
  ORDER BY i.id DESC
  LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);

// bind สำหรับคำนวณ is_owner                                           // [ADD]
$params[':cuid'] = (int)$cuid;

foreach ($params as $k=>$v) {
  if ($k === ':lim' || $k === ':off') continue;
  $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$CATS = [
  1=>'แฮนเมด', 2=>'ของประดิษฐ์', 3=>'ของใช้ทั่วไป',
  4=>'เสื้อผ้า', 5=>'หนังสือ', 6=>'ของสะสม'
];

// map ออกไปให้ FE ใช้                                                // [CHANGE]
$items = array_map(function($r) use($CATS){
  return [
    'id'            => (int)$r['id'],
    'title'         => (string)$r['title'],
    'category_id'   => (int)$r['category_id'],
    'category_name' => $CATS[(int)$r['category_id']] ?? '-',
    'province'      => $r['province'] ?: null,
    'cover'         => pub_url($r['cover'] ?? null),                   // ใช้ pub_url() [ADD]
    'user_id'       => isset($r['user_id']) ? (int)$r['user_id'] : null, // [ADD]
    'is_owner'      => ((int)($r['is_owner'] ?? 0) === 1),               // [ADD]
  ];
}, $rows ?? []);

json_ok([
  'items' => $items,
  'pagination' => [
    'page'   => $page,
    'limit'  => $limit,
    'total'  => $total,
    'pages'  => (int)ceil($total / max(1,$limit)),
  ]
]);
