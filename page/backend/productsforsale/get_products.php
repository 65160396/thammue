<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start(); // ใช้ตรวจเจ้าของ

// ===== Debug (ใช้เฉพาะตอนทดสอบ) =====
// error_reporting(E_ALL); ini_set('display_errors', 1);

// ===== DB =====
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ฟังก์ชันเช็คว่าตารางมีคอลัมน์หรือไม่ (กันพังถ้า schema ไม่ตรง)
function hasColumn(PDO $pdo, string $table, string $col): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $col]);
    return (int)$st->fetchColumn() > 0;
}
$hasProductUser = hasColumn($pdo, 'products', 'user_id');
$hasShopUser    = hasColumn($pdo, 'shops',    'user_id');

// ===== Params =====
$cat    = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$q      = trim($_GET['q'] ?? '');
$sort   = strtolower($_GET['sort'] ?? 'created');  // created|price|name
$dir    = strtolower($_GET['dir']  ?? 'desc');     // asc|desc
$limit  = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0; // 0 = all
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = $limit ? ($page - 1) * $limit : 0;

// ===== Sort map (ใช้ alias p.) =====
$sortMap = ['created' => 'p.created_at', 'price' => 'p.price', 'name' => 'p.name'];
$sortCol = $sortMap[$sort] ?? 'p.created_at';
$dirSql  = ($dir === 'asc') ? 'ASC' : 'DESC';

// ===== Image helpers =====
function detectPageRoot(): string
{
    $d = __DIR__;
    for ($i = 0; $i < 7; $i++) {
        if (is_dir($d . '/uploads')) return $d;
        $parent = dirname($d);
        if ($parent === $d) break;
        $d = $parent;
    }
    return __DIR__;
}
$PAGE_ROOT = detectPageRoot();
$WEB_PREFIX = '/page';

function productMainImageWebPath(array $p): string
{
    global $PAGE_ROOT, $WEB_PREFIX;
    if (!empty($p['main_image'])) {
        return (strpos($p['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $p['main_image']
            : $p['main_image'];
    }
    $dirFs = $PAGE_ROOT . "/uploads/products/{$p['id']}";
    if (is_dir($dirFs)) {
        $found = glob($dirFs . '/main_*.*');
        if ($found) return $WEB_PREFIX . "/uploads/products/{$p['id']}/" . basename($found[0]);
    }
    return $WEB_PREFIX . '/img/placeholder.png';
}

// ===== WHERE (อิง p.) =====
$where  = [];
$params = [];
if ($cat > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat;
}
if ($q !== '') {
    // หาในชื่อสินค้า/รายละเอียด และชื่อหมวด
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ===== COUNT =====
$stc = $pdo->prepare("SELECT COUNT(*)
                      FROM products p
                      LEFT JOIN categories c ON c.id = p.category_id
                      $whereSql");
$stc->execute($params);
$total = (int)$stc->fetchColumn();

// ===== MAIN =====
// owner fields เฉพาะเมื่อมีคอลัมน์จริง
$ownerSelectProduct = $hasProductUser ? 'p.user_id AS product_owner_id,' : '0 AS product_owner_id,';
$ownerSelectShop    = $hasShopUser    ? 's.user_id AS shop_owner_id,'    : '0 AS shop_owner_id,';

$sql = "
  SELECT
    p.id,
    $ownerSelectProduct
    p.name,
    p.price,
    p.main_image,
    p.category_id,
    p.created_at,
    s.province AS shop_province,
    $ownerSelectShop
    c.name AS category_name
  FROM products p
  LEFT JOIN shops s      ON s.id = p.shop_id
  LEFT JOIN categories c ON c.id = p.category_id
  $whereSql
  ORDER BY $sortCol $dirSql
";
if ($limit) $sql .= " LIMIT $limit OFFSET $offset";

$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll();

// ===== JSON =====
$items = [];
$me = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

foreach ($rows as $p) {
    $prodOwner = isset($p['product_owner_id']) ? (int)$p['product_owner_id'] : 0;
    $shopOwner = isset($p['shop_owner_id'])    ? (int)$p['shop_owner_id']    : 0;

    // เป็นเจ้าของเมื่อ user ปัจจุบันตรงกับ owner ของสินค้า หรือของร้าน
    $isOwner = ($me && ($me === $prodOwner || $me === $shopOwner));

    $items[] = [
        'id'             => (int)$p['id'],
        'name'           => $p['name'],
        'price'          => is_numeric($p['price']) ? (float)$p['price'] : $p['price'],
        'province'       => $p['shop_province'] ?: 'ไม่ระบุจังหวัด',
        'image'          => productMainImageWebPath($p),
        'category'       => (int)$p['category_id'],
        'category_name'  => $p['category_name'] ?: null,
        'created'        => $p['created_at'],
        'is_owner'       => $isOwner ? 1 : 0,
    ];
}

echo json_encode([
    'meta' => [
        'total' => $total,
        'page'  => $limit ? $page : 1,
        'limit' => $limit ?: $total,
        'sort'  => $sort,
        'dir'   => $dir
    ],
    'items' => $items
], JSON_UNESCAPED_UNICODE);
