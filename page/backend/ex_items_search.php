<?php
// /page/backend/ex_items_search.php — LIKE search ที่ง่ายสุด
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/ex__items_common.php'; // มี $mysqli, item_columns(), EX_ITEMS_TABLE
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function out($ok, $data = []) {
  echo json_encode(array_merge(['ok'=>$ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}
function num($v, $def){ $n = (int)$v; return $n >= 0 ? $n : $def; }

$q       = trim((string)($_GET['q'] ?? ''));
$limit   = min(max((int)($_GET['limit'] ?? 24), 1), 100);
$offset  = max((int)($_GET['offset'] ?? 0), 0);
$catId   = (int)($_GET['category_id'] ?? 0);
$prov    = trim((string)($_GET['province'] ?? ''));
$dist    = trim((string)($_GET['district'] ?? ''));
$subd    = trim((string)($_GET['subdistrict'] ?? ''));

$table = EX_ITEMS_TABLE;
$cols  = item_columns($mysqli);
$has   = fn($c) => in_array($c, $cols, true);

// SELECT list (กันสคีมาไม่ตรง)
$select = ['id','user_id','title'];
if ($has('description'))   $select[] = 'description';
if ($has('thumbnail_url')) $select[] = 'thumbnail_url';
if ($has('category_id'))   $select[] = 'category_id';
if ($has('province'))      $select[] = 'province';
if ($has('district'))      $select[] = 'district';
if ($has('subdistrict'))   $select[] = 'subdistrict';
if ($has('zipcode'))       $select[] = 'zipcode';
if ($has('created_at'))    $select[] = 'created_at';

// WHERE: q → แตกคำเว้นวรรค แล้ว LIKE title/description
$where = ['1'];
$args  = [];
$types = '';

if ($q !== '') {
  $tokens = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
  // จำกัดไม่เกิน 5 คำ เพื่อ SQL ไม่ยาวเกิน
  $tokens = array_slice($tokens, 0, 5);
  foreach ($tokens as $t) {
    $w = [];
    if ($has('title'))        { $w[] = 'title LIKE ?';        $types.='s'; $args[]='%'.$t.'%'; }
    if ($has('description'))  { $w[] = 'description LIKE ?';  $types.='s'; $args[]='%'.$t.'%'; }
    if (!$w) continue;
    $where[] = '('.implode(' OR ', $w).')';
  }
}

if ($catId > 0 && $has('category_id')) { $where[]='category_id=?'; $types.='i'; $args[]=$catId; }
if ($prov !== '' && $has('province'))   { $where[]='province LIKE ?';   $types.='s'; $args[]='%'.$prov.'%'; }
if ($dist !== '' && $has('district'))   { $where[]='district LIKE ?';   $types.='s'; $args[]='%'.$dist.'%'; }
if ($subd !== '' && $has('subdistrict')){ $where[]='subdistrict LIKE ?';$types.='s'; $args[]='%'.$subd.'%'; }

$orderBy = $has('created_at') ? 'created_at DESC' : 'id DESC';

// สร้าง SQL
$sql = "SELECT ".implode(',', $select)."
        FROM `$table`
        WHERE ".implode(' AND ', $where)."
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$types .= 'ii';
$args[] = $limit;
$args[] = $offset;

try {
  $st = $mysqli->prepare($sql);
  if ($types !== '') { $st->bind_param($types, ...$args); }
  $st->execute();
  $rs = $st->get_result();
  $items = [];
  while ($r = $rs->fetch_assoc()) {
    // alias ให้ฝั่งหน้าเว็บหยิบได้เหมือนเดิม
    if (isset($r['province'])   && !isset($r['addr_province']))   $r['addr_province']   = $r['province'];
    if (isset($r['subdistrict'])&& !isset($r['addr_subdistrict']))$r['addr_subdistrict']= $r['subdistrict'];
    $items[] = $r;
  }
  out(true, ['items'=>$items, 'limit'=>$limit, 'offset'=>$offset]);
} catch (Throwable $e) {
  out(false, ['error'=>'db_failed', 'detail'=>$e->getMessage()]);
}
