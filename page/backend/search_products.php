<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

function columnExists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$col]);
    return (bool)$stmt->fetch();
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true, // ให้ PHP แทนค่า → DB ไม่เห็น ?
        ]
    );

    $q        = trim($_GET['q'] ?? '');
    $catId    = (int)($_GET['category'] ?? 0);
    $priceMin = is_numeric($_GET['price_min'] ?? null) ? (float)$_GET['price_min'] : null;
    $priceMax = is_numeric($_GET['price_max'] ?? null) ? (float)$_GET['price_max'] : null;
    $sort     = strtolower($_GET['sort'] ?? 'relevance');
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(60, max(1, (int)($_GET['per'] ?? 24)));
    $offset   = ($page - 1) * $perPage;

    $where   = [];
    $bind    = [];
    $joinPCM = "";

    // กรอง is_active ถ้ามีคอลัมน์
    if (columnExists($pdo, 'products', 'is_active')) {
        $where[] = "p.is_active = 1";
    }

    if ($catId > 0) {
        $joinPCM = "LEFT JOIN product_category_map pcm ON pcm.product_id = p.id";
        $where[] = "pcm.category_id = :catId";
        $bind[':catId'] = $catId;
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
        $where[] = "(p.name LIKE :q1 OR p.description LIKE :q2)";
        $bind[':q1'] = "%$q%";
        $bind[':q2'] = "%$q%";
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // นับจำนวน
    $sqlCount = "SELECT COUNT(DISTINCT p.id) AS total FROM products p $joinPCM $whereSql";
    $st = $pdo->prepare($sqlCount);
    $st->execute($bind);
    $total = (int)$st->fetchColumn();

    // เรียงลำดับ
    $orderBy = "ORDER BY p.created_at DESC";
    if ($sort === 'price_asc')  $orderBy = "ORDER BY p.price ASC";
    if ($sort === 'price_desc') $orderBy = "ORDER BY p.price DESC";
    if ($sort === 'newest')     $orderBy = "ORDER BY p.created_at DESC";

    // ดึงรายการ
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
    $joinPCM
    $whereSql
    $orderBy
    LIMIT $per OFFSET $off
  ";

    // debug
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        echo json_encode(['debug' => true, 'sqlCount' => $sqlCount, 'sql' => $sql, 'bind' => $bind], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $items = $st->fetchAll();

    // ✅ normalize path ให้แน่ใจว่าเป็น forward slash เสมอ + ค่า fallback
    foreach ($items as &$it) {
        $p = $it['main_image'] ?? '';
        if ($p === '' || $p === null) {
            $p = '/img/placeholder.png';
        }
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#/+#', '/', $p);
        // เพิ่ม / นำหน้า เฉพาะเมื่อไม่ใช่ URL และไม่ว่าง
        if ($p !== '' && !preg_match('#^https?://#i', $p) && $p[0] !== '/') {
            $p = '/' . ltrim($p, '/');
        }
        $it['main_image'] = $p;
    }


    // ✅ ถ้าไฟล์จริงอยู่ใต้ /page/uploads ให้ชดเชยพาธอัตโนมัติ
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', "/\\");
    foreach ($items as &$it) {
        $p = $it['main_image'] ?? '';
        if ($p === '' || $p === null) {
            $p = '/img/placeholder.png';
        }
        // แทน backslash และลด // ซ้อน
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#/+#', '/', $p);
        // เติม / นำหน้าถ้าจำเป็น
        if ($p !== '' && !preg_match('#^https?://#i', $p) && $p[0] !== '/') {
            $p = '/' . ltrim($p, '/');
        }

        // ⬇️ ถ้าไฟล์ไม่เจอที่ /uploads/... แต่เจอที่ /page/uploads/... ให้เปลี่ยน URL
        if (preg_match('#^/uploads/#', $p)) {
            if ($docroot && !is_file($docroot . $p) && is_file($docroot . '/page' . $p)) {
                $p = '/page' . $p;
            }
        }

        // กันกรณีสุดท้าย ไม่เจอไฟล์จริงให้ใช้ placeholder
        if ($docroot && !preg_match('#^https?://#i', $p) && !is_file($docroot . $p)) {
            $p = '/img/placeholder.png';
        }

        $it['main_image'] = $p;
    }
    unset($it); // ปลดอ้างอิง




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
