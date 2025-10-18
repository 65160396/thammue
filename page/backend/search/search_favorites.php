<?php
// /page/backend/search_favorites.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $userId   = (int)$_SESSION['user_id'];
    $q        = trim($_GET['q'] ?? '');
    $sort     = strtolower($_GET['sort'] ?? 'relevance');
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(60, max(1, (int)($_GET['per'] ?? 24)));
    $offset   = ($page - 1) * $perPage;

    // ===== WHERE / BIND
    $where = ["f.user_id = :uid", "f.item_type = 'product'"];
    $bind  = [':uid' => $userId];

    if ($q !== '') {
        // LIKE ง่าย ๆ (ถ้าอยากแรงมากค่อยทำ FULLTEXT ทีหลัง)
        $where[]    = "(p.name LIKE :q OR p.description LIKE :q)";
        $bind[':q'] = "%$q%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // ===== ORDER BY
    $orderBy = "ORDER BY f.created_at DESC"; // default = รายการโปรดล่าสุดขึ้นก่อน
    if ($sort === 'price_asc')  $orderBy = "ORDER BY p.price ASC";
    if ($sort === 'price_desc') $orderBy = "ORDER BY p.price DESC";
    if ($sort === 'newest')     $orderBy = "ORDER BY p.created_at DESC";

    // ===== COUNT
    $sqlCount = "SELECT COUNT(DISTINCT p.id)
               FROM favorites f
               JOIN products p ON p.id = f.item_id AND f.item_type = 'product'
               $whereSql";
    $st = $pdo->prepare($sqlCount);
    $st->execute($bind);
    $total = (int)$st->fetchColumn();

    // ===== MAIN QUERY
    $per = (int)$perPage;
    $off = (int)$offset;

    $sql = "
    SELECT DISTINCT
      p.id,
      p.name,
      p.price,
      p.created_at,
      COALESCE(NULLIF(REPLACE(p.main_image, '\\\\', '/'), ''), '/img/placeholder.png') AS main_image,
      s.province,
      -- สถานะในตะกร้า (ถ้าอยากโชว์ไอคอน)
      IF(c.product_id IS NULL, 0, 1) AS in_cart
    FROM favorites f
    JOIN products p ON p.id = f.item_id AND f.item_type = 'product'
    LEFT JOIN shops s ON s.id = p.shop_id
    LEFT JOIN cart  c ON c.user_id = :uid AND c.product_id = p.id
    $whereSql
    $orderBy
    LIMIT $per OFFSET $off
  ";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $items = $st->fetchAll();

    // ===== รูป: normalize + กันไฟล์หาย (เหมือนตัว search_products.php)
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
