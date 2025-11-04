<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

/* ------------------------------------------------------------------
    Utilities (ฟังก์ชันช่วย)
-------------------------------------------------------------------*/
/** ตรวจว่าคอลัมน์มีหรือไม่ */
function columnExists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$col]);
    return (bool)$stmt->fetch();
}
/** ตรวจว่าตารางมีหรือไม่ */
function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

try {
    /* ------------------------------------------------------------------
         ✅ เชื่อมต่อฐานข้อมูล
    -------------------------------------------------------------------*/
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]
    );

    /* ------------------------------------------------------------------
         ✅ รับค่าพารามิเตอร์จาก URL (filter / search / sort / paginate)
    -------------------------------------------------------------------*/
    $q         = trim($_GET['q'] ?? '');
    $catId     = (int)($_GET['category'] ?? 0);   // ถ้ามี ?category= จะให้สิทธิ์สูงกว่า slug
    $catSlug   = trim($_GET['cat_slug'] ?? '');
    $priceMin  = is_numeric($_GET['price_min'] ?? null) ? (float)$_GET['price_min'] : null;
    $priceMax  = is_numeric($_GET['price_max'] ?? null) ? (float)$_GET['price_max'] : null;
    $sort      = strtolower($_GET['sort'] ?? 'relevance');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(60, max(1, (int)($_GET['per'] ?? 24)));
    $offset    = ($page - 1) * $perPage;

    /* ------------------------------------------------------------------
         ✅ สร้างโครง query เงื่อนไขพื้นฐาน
    -------------------------------------------------------------------*/
    $where = [];
    $bind  = [];
    $joins = [];

    $hasPCM       = tableExists($pdo, 'product_category_map');       // ตาราง map
    $hasProdCatId = columnExists($pdo, 'products', 'category_id');   // products.category_id

    /* ------------------------------------------------------------------
       เงื่อนไขพื้นฐาน
    -------------------------------------------------------------------*/
    if (columnExists($pdo, 'products', 'is_active')) {
        $where[] = "COALESCE(p.is_active, 1) = 1";
    }
    if ($priceMin !== null) {
        $where[] = "p.price >= :pmin";
        $bind[':pmin'] = $priceMin;
    }
    if ($priceMax !== null) {
        $where[] = "p.price <= :pmax";
        $bind[':pmax'] = $priceMax;
    }

    if ($q !== '') {
        $where[]    = "(p.name LIKE :q1 OR p.description LIKE :q2)";
        $bind[':q1'] = "%$q%";
        $bind[':q2'] = "%$q%";
    }

    /* ------------------------------------------------------------------
       กรองหมวดด้วย slug โดยตรง (จาก categories.slug)
    -------------------------------------------------------------------*/
    $catIdEff = 0;

    if ($catSlug !== '') {
        $st = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
        $st->execute([':slug' => $catSlug]);
        $catIdEff = (int)($st->fetchColumn() ?: 0);
        // ถ้า slug ไม่เจอ -> ตั้งให้เป็น 0 แล้วไม่เติมเงื่อนไข (ผลจะเป็นทั้งระบบ)
        // ถ้าอยากบังคับ "ไม่พบผลลัพธ์" ให้เพิ่ม $where[] = "1=0";
    }

    // ถ้ามี ?category= มา ให้ override
    if ($catId > 0) {
        $catIdEff = $catId;
    }

    if ($catIdEff > 0) {
        if ($hasPCM && $hasProdCatId) {
            // รองรับทั้งสอง schema: map และคอลัมน์ตรง
            $joins[]  = "LEFT JOIN product_category_map pcm ON pcm.product_id = p.id";
            $where[]  = "(pcm.category_id = :catEff OR p.category_id = :catEff)";
            $bind[':catEff'] = $catIdEff;
        } elseif ($hasPCM) {
            $joins[]  = "LEFT JOIN product_category_map pcm ON pcm.product_id = p.id";
            $where[]  = "pcm.category_id = :catEff";
            $bind[':catEff'] = $catIdEff;
        } elseif ($hasProdCatId) {
            $where[]  = "p.category_id = :catEff";
            $bind[':catEff'] = $catIdEff;
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $joinSql  = $joins ? implode(' ', $joins) : '';

    /* ------------------------------------------------------------------
        ✅ นับจำนวนสินค้าทั้งหมด (สำหรับแบ่งหน้า)
    -------------------------------------------------------------------*/
    $sqlCount = "SELECT COUNT(DISTINCT p.id) AS total
                 FROM products p
                 $joinSql
                 $whereSql";
    $st = $pdo->prepare($sqlCount);
    $st->execute($bind);
    $total = (int)$st->fetchColumn();

    /* ------------------------------------------------------------------
       ✅ การเรียงลำดับผลลัพธ์ (ORDER BY)
    -------------------------------------------------------------------*/
    $orderBy = "ORDER BY p.created_at DESC";
    if ($sort === 'price_asc')  $orderBy = "ORDER BY p.price ASC";
    if ($sort === 'price_desc') $orderBy = "ORDER BY p.price DESC";
    if ($sort === 'newest')     $orderBy = "ORDER BY p.created_at DESC";

    /* ------------------------------------------------------------------
        ✅ ดึงข้อมูลสินค้าจริง
    -------------------------------------------------------------------*/
    $per = (int)$perPage;
    $off = (int)$offset;

    $sql = "
        SELECT DISTINCT
            p.id,
            p.name,
            p.price,
            p.created_at,
            COALESCE(NULLIF(REPLACE(p.main_image, '\\\\', '/'), ''), '/img/placeholder.png') AS main_image,
            p.category_id
        FROM products p
        $joinSql
        $whereSql
        $orderBy
        LIMIT $per OFFSET $off
    ";

    // debug ?debug=1
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        echo json_encode([
            'debug'    => true,
            'sqlCount' => $sqlCount,
            'sql'      => $sql,
            'bind'     => $bind
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $items = $st->fetchAll();

    /* ------------------------------------------------------------------
       ✅ จัดการ path ของรูปภาพ (กันรูปหาย / แก้ path)
    -------------------------------------------------------------------*/
    foreach ($items as &$it) {
        $p = $it['main_image'] ?? '';
        if ($p === '' || $p === null) $p = '/img/placeholder.png';
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#/+#', '/', $p);
        if ($p !== '' && !preg_match('#^https?://#i', $p) && $p[0] !== '/') {
            $p = '/' . ltrim($p, '/');
        }
        $it['main_image'] = $p;
    }

    // ชดเชย path /page/uploads และกันไฟล์หาย
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', "/\\");
    foreach ($items as &$it) {
        $p = $it['main_image'] ?? '';
        if ($p === '' || $p === null) $p = '/img/placeholder.png';
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#/+#', '/', $p);
        if ($p !== '' && !preg_match('#^https?://#i', $p) && $p[0] !== '/') {
            $p = '/' . ltrim($p, '/');
        }
        if (preg_match('#^/uploads/#', $p)) {
            if ($docroot && !is_file($docroot . $p) && is_file($docroot . '/page' . $p)) {
                $p = '/page' . $p;
            }
        }
        if ($docroot && !preg_match('#^https?://#i', $p) && !is_file($docroot . $p)) {
            $p = '/img/placeholder.png';
        }
        $it['main_image'] = $p;
    }
    unset($it);

    /* ------------------------------------------------------------------
       Response
    -------------------------------------------------------------------*/
    echo json_encode([
        'ok'       => true,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'items'    => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
