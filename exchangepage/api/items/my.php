<?php
// /thammue/api/items/my.php
declare(strict_types=1);
require __DIR__ . '/../_config.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// --- บังคับตอบ JSON ล้วนและจับ error กลับเป็น JSON ---
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
while (ob_get_level()) { ob_end_clean(); }
set_error_handler(function($no,$str,$file,$line){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'PHP','msg'=>$str,'at'=>basename($file).":$line"], JSON_UNESCAPED_UNICODE);
  exit;
});
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'EXC','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
});

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$uid = me_id();
if (!$uid) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'AUTH']); exit; }

// ---------- helpers ----------
function table_exists(PDO $pdo, string $name): bool {
  $q = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($name));
  return (bool)$q->fetchColumn();
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
  $st->execute([':c'=>$col]);
  return (bool)$st->fetch();
}
if (!function_exists('pub_url')) {
  function pub_url(?string $p): ?string {
    if (!$p) return null;
    $p = trim($p);
    if ($p === '') return null;
    if (preg_match('#^https?://#i', $p)) return $p;
    if ($p[0] === '/') return $p;
    return THAMMUE_BASE . '/uploads/' . ltrim($p, '/');
  }
}

// ---------- paging/query ----------
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
$off  = ($page - 1) * $per;
$q    = trim((string)($_GET['q'] ?? ''));

// ---------- map คอลัมน์ตามที่มีจริง ----------
$hasTitle    = col_exists($pdo, 'items', 'title');
$hasName     = col_exists($pdo, 'items', 'name');
$hasCatId    = col_exists($pdo, 'items', 'category_id');
$hasProvince = col_exists($pdo, 'items', 'province');
$hasStatus   = col_exists($pdo, 'items', 'status');

$coverCol    = col_exists($pdo, 'items', 'cover') ? 'i.cover'
             : (col_exists($pdo, 'items', 'image') ? 'i.image'
             : (col_exists($pdo, 'items', 'photo') ? 'i.photo' : 'NULL'));

$titleSel = $hasTitle ? 'i.title' : ($hasName ? 'i.name' : 'NULL');
$catSel   = $hasCatId ? 'i.category_id' : 'NULL';
$provSel  = $hasProvince ? 'i.province' : 'NULL';
$statSel  = $hasStatus ? 'i.status' : 'NULL';

$hasCatTable = table_exists($pdo, 'categories');
$selectCat   = $hasCatTable ? 'c.name AS category_name' : 'NULL AS category_name';
$joinCat     = $hasCatTable ? ' LEFT JOIN categories c ON c.id = i.category_id ' : '';

// ---------- count ----------
if ($q !== '' && ($hasTitle || $hasName)) {
  $likeCol = $hasTitle ? 'title' : 'name';
  $stC = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id=:u AND `$likeCol` LIKE :q");
  $stC->execute([':u'=>$uid, ':q'=>'%'.$q.'%']);
} else {
  $stC = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id=:u");
  $stC->execute([':u'=>$uid]);
}
$total = (int)$stC->fetchColumn();

// ---------- list ----------
$sql = "
  SELECT 
    i.id,
    {$titleSel}  AS title,
    {$catSel}    AS category_id,
    {$provSel}   AS province,
    {$coverCol}  AS cover,
    {$statSel}   AS status,
    {$selectCat}
  FROM items i
  {$joinCat}
  WHERE i.user_id=:u
";
$params = [':u'=>$uid];

if ($q !== '' && ($hasTitle || $hasName)) {
  $likeExpr = $hasTitle ? 'i.title' : 'i.name';
  $sql .= " AND {$likeExpr} LIKE :q ";
  $params[':q'] = '%'.$q.'%';
}

$sql .= " ORDER BY i.id DESC LIMIT :lim OFFSET :off ";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v){
  if ($k===':lim' || $k===':off') continue;
  $st->bindValue($k, $v);
}
$st->bindValue(':lim', $per, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();

// ---------- images ----------
$hasImgs = table_exists($pdo, 'item_images');
$rows = [];
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  $imgs = [];
  if ($hasImgs) {
    $stImg = $pdo->prepare("SELECT path FROM item_images WHERE item_id=:id ORDER BY id ASC");
    $stImg->execute([':id'=>$r['id']]);
    $imgs = array_map(fn($a)=>pub_url($a['path']), $stImg->fetchAll(PDO::FETCH_ASSOC));
  }
  $coverUrl = !empty($r['cover']) ? pub_url($r['cover']) : ($imgs[0] ?? null);

  $rows[] = [
    'id'            => (int)$r['id'],
    'title'         => $r['title'],
    'category_id'   => isset($r['category_id']) ? (int)$r['category_id'] : 0,
    'category_name' => $r['category_name'] ?? null,
    'province'      => $r['province'] ?? null,
    'cover'         => $coverUrl,
    'images'        => $imgs,
    'status'        => $r['status'] ?? null,
    'is_owner'      => true,
  ];
}

echo json_encode([
  'ok'       => true,
  'page'     => $page,
  'per_page' => $per,
  'total'    => $total,
  'items'    => $rows
], JSON_UNESCAPED_UNICODE);
exit;
