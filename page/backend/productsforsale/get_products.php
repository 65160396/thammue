<?php
// get_products.php (merged + robust image path)
// ตอบ JSON รายการสินค้า, รองรับกรอง/ค้นหา/เรียง, และแก้ path รูปให้ชี้ /page/uploads/...

header('Content-Type: application/json; charset=utf-8');
session_start();

// ===== Debug ชั่วคราว =====
// error_reporting(E_ALL); ini_set('display_errors', 1);

// ========== DB ==========
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ========== รับพารามิเตอร์ ==========
$cat   = isset($_GET['cat'])  ? (int)$_GET['cat']  : 0;           // filter by category_id
$q     = trim($_GET['q'] ?? '');                                  // search (name/description)
$sort  = strtolower($_GET['sort'] ?? 'created');                  // created|price|name
$dir   = strtolower($_GET['dir']  ?? 'desc');                     // asc|desc
$limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0;  // 0 = no limit (all)
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = $limit ? ($page - 1) * $limit : 0;

// ========== map sort ==========
$sortMap = ['created' => 'created_at', 'price' => 'price', 'name' => 'name'];
$sortCol = $sortMap[$sort] ?? $sortMap['created'];
$dirSql  = ($dir === 'asc') ? 'ASC' : 'DESC';

// ========== ค้นหา page root + ตั้งค่าเว็บพาธ ==========
/*
  โปรเจกต์ของคุณเก็บรูปไว้: /page/uploads/products/{id}/main_*.jpg
  และหน้าเว็บรันใต้ /page/...
  เราจะตรวจหาโฟลเดอร์ /uploads จากตำแหน่งไฟล์นี้ แล้วตั้ง $PAGE_ROOT และ $WEB_PREFIX ให้ถูกเอง
*/
function detectPageRoot(): string
{
    $d = __DIR__;
    for ($i = 0; $i < 7; $i++) {                // ไต่ขึ้นไม่เกิน 7 ชั้น
        if (is_dir($d . '/uploads')) return $d;
        $parent = dirname($d);
        if ($parent === $d) break;
        $d = $parent;
    }
    return __DIR__; // fallback
}
$PAGE_ROOT = detectPageRoot();
$WEB_PREFIX = '/page';                     // เว็บคุณเสิร์ฟใต้ /page/* (ถ้าย้ายไป root ให้เปลี่ยนเป็น '')

// ========== helper หา path รูปหลัก ==========
function productMainImageWebPath(array $p): string
{
    // เข้าถึงตัวแปรนอกฟังก์ชัน
    global $PAGE_ROOT, $WEB_PREFIX;

    // 1) ถ้าใน DB มี path แล้ว
    if (!empty($p['main_image'])) {
        $path = $p['main_image'];
        // ถ้าเริ่มด้วย /uploads/... ให้เติม /page นำหน้า
        if (strpos($path, '/uploads/') === 0) return $WEB_PREFIX . $path;
        // ถ้า DB เก็บมาถูกอยู่แล้ว (เช่น /page/uploads/...) ส่งกลับเลย
        return $path;
    }

    // 2) เดาจากโฟลเดอร์จริง: {PAGE_ROOT}/uploads/products/{id}/main_*.* 
    $dirFs = $PAGE_ROOT . "/uploads/products/{$p['id']}";
    if (is_dir($dirFs)) {
        $found = glob($dirFs . "/main_*.*");
        if ($found) {
            return $WEB_PREFIX . "/uploads/products/{$p['id']}/" . basename($found[0]);
        }
    }

    // 3) fallback
    return $WEB_PREFIX . "/img/placeholder.png";
}

// ========== build WHERE ==========
$where = [];
$params = [];
if ($cat > 0) {
    $where[] = 'category_id = ?';
    $params[] = $cat;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ========== count total ==========
$stc = $pdo->prepare("SELECT COUNT(*) FROM products $whereSql");
$stc->execute($params);
$total = (int)$stc->fetchColumn();

// ========== main query ==========
$sql = "SELECT id, name, price, province, main_image, category_id, created_at
        FROM products
        $whereSql
        ORDER BY $sortCol $dirSql";
if ($limit) $sql .= " LIMIT $limit OFFSET $offset";
$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll();

// ========== shape JSON ==========
$items = [];
foreach ($rows as $p) {
    $items[] = [
        'id'       => (int)$p['id'],
        'name'     => $p['name'],
        'price'    => is_numeric($p['price']) ? (float)$p['price'] : $p['price'],
        'province' => $p['province'] ?: 'ไม่ระบุจังหวัด',
        'image'    => productMainImageWebPath($p),                 // <-- พาธรูปพร้อม /page/...
        'category' => (int)$p['category_id'],
        'created'  => $p['created_at'],
    ];
}

echo json_encode([
    'meta'  => [
        'total' => $total,
        'page'  => $limit ? $page : 1,
        'limit' => $limit ?: $total,
        'sort'  => $sort,
        'dir'   => $dir
    ],
    'items' => $items
], JSON_UNESCAPED_UNICODE);
