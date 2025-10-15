<?php
// /page/store/store_public.php
header('Content-Type: text/html; charset=utf-8');
// เพจสาธารณะ ไม่จำเป็นต้องใช้ session
// session_start();

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$shopId  = isset($_GET['id'])  ? (int)$_GET['id']  : 0;
$catId   = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;   // 0 = ทุกหมวด
$q       = trim($_GET['q'] ?? '');                        // ค้นหาชื่อสินค้า (ถ้ามี)
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// ---------- ข้อมูลร้าน ----------
$stmt = $pdo->prepare("
    SELECT id, name, province, avatar_url, description
    FROM shops WHERE id=? LIMIT 1
");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();
if (!$shop) {
    http_response_code(404);
    echo "<h2>ไม่พบร้านค้า</h2>";
    exit;
}

// ---------- หมวดหมู่ที่ร้านนี้มี ----------
/* โครงฐานข้อมูลสมมติ:
   products(id, name, price, thumb_url, shop_id, category_id, created_at)
   categories(id, name)
   (ไม่มีคอลัมน์ status)
*/
$cats = $pdo->prepare("
  SELECT c.id, c.name, COUNT(p.id) AS cnt
  FROM categories c
  LEFT JOIN products p
         ON p.category_id = c.id
        AND p.shop_id = ?
  GROUP BY c.id, c.name
  HAVING cnt > 0
  ORDER BY c.name
");
$cats->execute([$shopId]);
$categories = $cats->fetchAll();

// ---------- นับจำนวนสินค้าทั้งหมด (ตามเงื่อนไขกรอง) ----------
$where  = "p.shop_id = :shop";
$params = ['shop' => $shopId];

if ($catId > 0) {
    $where .= " AND p.category_id = :cat";
    $params['cat'] = $catId;
}
if ($q !== '') {
    $where .= " AND p.name LIKE :q";
    $params['q'] = "%$q%";
}

$countSql = "SELECT COUNT(*) FROM products p WHERE $where";
$stc = $pdo->prepare($countSql);
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// กัน page เกินช่วง (เช่นพิมพ์ ?page=9999)
if ($page > $pages) {
    $page   = $pages;
    $offset = ($page - 1) * $perPage;
}

// ---------- ดึงสินค้าหน้าปัจจุบัน ----------
$listSql = "SELECT p.id, p.name, p.price, p.thumb_url
            FROM products p
            WHERE $where
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";
$stl = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $type = ($k === 'cat' || $k === 'shop') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stl->bindValue(':' . $k, $v, $type);
}
$stl->bindValue(":limit",  $perPage, PDO::PARAM_INT);
$stl->bindValue(":offset", $offset,  PDO::PARAM_INT);
$stl->execute();
$items = $stl->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($shop['name']) ?> – ร้านค้า | THAMMUE</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/open-shop.css" />
    <style>
        .shop-hero {
            display: flex;
            gap: 16px;
            align-items: center;
            padding: 16px 0
        }

        .shop-hero img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover
        }

        .shop-hero .name {
            font-size: 24px;
            font-weight: 700
        }

        .cat-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0 20px
        }

        .cat-tab {
            padding: 8px 12px;
            border: 1px solid #eee;
            border-radius: 999px;
            background: #fff
        }

        .cat-tab.active {
            border-color: #111;
            background: #111;
            color: #fff
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px
        }

        .card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden
        }

        .card img {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            background: #f8f8f8
        }

        .card .p {
            padding: 10px
        }

        .price {
            font-weight: 700
        }

        .toolbar {
            display: flex;
            gap: 8px;
            align-items: center;
            margin: 8px 0 16px
        }

        .pager {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin: 18px 0
        }

        .pager a {
            padding: 8px 12px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: #fff
        }

        .pager .current {
            font-weight: 700
        }
    </style>
</head>

<body class="open-shop">
    <header class="topbar">
        <a href="/page/main.html" class="brand">THAMMUE</a>
    </header>

    <main class="wrap">
        <!-- HERO ร้าน -->
        <section class="shop-hero">
            <img src="<?= htmlspecialchars($shop['avatar_url'] ?? '/img/shop-default.png') ?>" alt="">
            <div>
                <div class="name"><?= htmlspecialchars($shop['name']) ?></div>
                <div class="meta">จังหวัด<?= htmlspecialchars($shop['province'] ?? '-') ?></div>
                <?php if (!empty($shop['description'])): ?>
                    <div class="desc" style="color:#666"><?= nl2br(htmlspecialchars($shop['description'])) ?></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- เครื่องมือค้นหาเล็กๆ ในร้าน -->
        <form class="toolbar" method="get">
            <input type="hidden" name="id" value="<?= (int)$shopId ?>">
            <?php if ($catId > 0): ?><input type="hidden" name="cat" value="<?= (int)$catId ?>"><?php endif; ?>
            <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="ค้นหาสินค้าในร้านนี้" style="flex:1;padding:10px;border-radius:10px;border:1px solid #ddd">
            <button type="submit" class="btn-primary">ค้นหา</button>
            <?php if ($q !== ''): ?>
                <a class="btn" href="?id=<?= (int)$shopId ?><?= $catId ? '&cat=' . $catId : '' ?>">ล้างค้นหา</a>
            <?php endif; ?>
        </form>

        <!-- แถบหมวดหมู่ -->
        <nav class="cat-tabs">
            <a class="cat-tab <?= $catId === 0 ? 'active' : '' ?>" href="?id=<?= (int)$shopId ?>">ทั้งหมด (<?= $total ?>)</a>
            <?php foreach ($categories as $c): ?>
                <a class="cat-tab <?= $catId === (int)$c['id'] ? 'active' : '' ?>"
                    href="?id=<?= (int)$shopId ?>&cat=<?= (int)$c['id'] ?>">
                    <?= htmlspecialchars($c['name']) ?> (<?= (int)$c['cnt'] ?>)
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- สินค้า -->
        <?php if (!$items): ?>
            <p>ยังไม่มีสินค้าตามเงื่อนไขนี้</p>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($items as $it): ?>
                    <a class="card" href="/page/products/product_detail.php?id=<?= (int)$it['id'] ?>">
                        <img src="<?= htmlspecialchars($it['thumb_url'] ?? '/img/noimg.png') ?>" alt="">
                        <div class="p">
                            <div class="title" style="min-height:44px"><?= htmlspecialchars($it['name']) ?></div>
                            <div class="price">$<?= number_format((float)$it['price'], 0) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>

            <!-- แบ่งหน้า -->
            <div class="pager">
                <?php for ($i = 1; $i <= $pages; $i++):
                    $qs = http_build_query(array_filter([
                        'id' => $shopId,
                        'cat' => $catId ?: null,
                        'q' => $q ?: null,
                        'page' => $i
                    ]));
                    $href = '?' . $qs;
                ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $href ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>