<?php
// /page/store/store_public.php
header('Content-Type: text/html; charset=utf-8');

// ===== DB =====
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ===== Params =====
$shopId  = isset($_GET['id'])  ? (int)$_GET['id']  : 0;
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 18;
$offset  = ($page - 1) * $perPage;

// ===== PATH helper ของรูป =====
$WEB_PREFIX = '/page';
function productImageWeb(int $productId, ?string $imagePath): string
{
    global $WEB_PREFIX;
    if ($imagePath && preg_match('~^https?://~i', $imagePath)) return $imagePath; // URL เต็ม
    if ($imagePath && strpos($imagePath, '/uploads/') === 0)  return $WEB_PREFIX . $imagePath; // เริ่มด้วย /uploads/
    if ($imagePath && strpos($imagePath, '/') !== false)      return $WEB_PREFIX . '/' . ltrim($imagePath, '/'); // พาธสัมพัทธ์

    // เก็บมาเป็นชื่อไฟล์ล้วน
    if ($imagePath) return $WEB_PREFIX . "/uploads/products/{$productId}/" . $imagePath;

    // fallback: ลองหาไฟล์ในโฟลเดอร์ของสินค้า
    $dirFs = realpath(__DIR__ . "/../uploads/products/" . $productId);
    if ($dirFs && is_dir($dirFs)) {
        $main = glob($dirFs . "/main_*.*");
        if ($main) return $WEB_PREFIX . "/uploads/products/{$productId}/" . basename($main[0]);
        $any  = glob($dirFs . "/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}", GLOB_BRACE);
        if ($any)  return $WEB_PREFIX . "/uploads/products/{$productId}/" . basename($any[0]);
    }
    return $WEB_PREFIX . "/img/noimg.png";
}
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ===== ข้อมูลร้าน =====
$stShop = $pdo->prepare("
  SELECT id, shop_name, province, pickup_addr
  FROM shops
  WHERE id = ?
  LIMIT 1
");
$stShop->execute([$shopId]);
$shop = $stShop->fetch();

if (!$shop) {
    http_response_code(404);
    echo "<h2 style='font-family:sans-serif'>ไม่พบร้านค้า</h2>";
    exit;
}

$shopName     = $shop['shop_name'] ?: 'ไม่ระบุชื่อร้าน';
$shopProvince = $shop['province']   ?: '-';

// ===== เงื่อนไขค้นหา =====
$where  = "p.shop_id = :shop";
$params = ['shop' => $shopId];

if ($q !== '') {
    $where .= " AND p.name LIKE :q";
    $params['q'] = "%$q%";
}

// ===== นับจำนวน =====
$stCount = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $perPage;
}

// ===== รายการสินค้า =====
$sqlList = "
  SELECT
    p.id, p.name, p.price,
    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
  FROM products p
  WHERE $where
  ORDER BY COALESCE(p.created_at, p.id) DESC
  LIMIT :limit OFFSET :offset
";
$stList = $pdo->prepare($sqlList);
foreach ($params as $k => $v) {
    $stList->bindValue(':' . $k, $v, ($k === 'shop') ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stList->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stList->execute();
$items = $stList->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= h($shopName) ?> | THAMMUE</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/store_public.css" />
</head>

<body class="store-public">
    <?php // === แถบบนเดียวกับหน้า product_detail === 
    $HEADER_NO_CATS = true;
    ?>

    <?php include __DIR__ . '/../partials/site-header.php'; ?>

    <main class="wrap">
        <!-- หัวร้านแบบสั้นๆ -->
        <section class="shop-hero">
            <img src="/img/shop-default.png" alt="" />
            <div>
                <div class="name"><?= h($shopName) ?></div>
                <div class="meta">จังหวัด<?= h($shopProvince) ?></div>
            </div>
        </section>

        <!-- แถวหัวข้อ + ช่องค้นหา -->
        <div class="heading-row">
            <div class="title">สินค้าทั้งหมด (<?= number_format($total) ?>)</div>
            <form method="get">
                <input type="hidden" name="id" value="<?= (int)$shopId ?>">
                <?php if ($q !== ''): ?>
                    <a class="btn" href="?id=<?= (int)$shopId ?>">ล้าง</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$items): ?>
            <p>ยังไม่มีสินค้า</p>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($items as $it):
                    $imgWeb = productImageWeb((int)$it['id'], $it['image_path'] ?? null);
                ?>
                    <a class="card" href="/page/products/product_detail.php?id=<?= (int)$it['id'] ?>">
                        <img src="<?= h($imgWeb) ?>" alt="">
                        <div class="p">
                            <div class="title"><?= h($it['name']) ?></div>
                            <div class="price">฿<?= number_format((float)$it['price'], 0) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>


        <?php endif; ?>
    </main>

    <!-- สคริปต์เดียวกับหน้า product_detail เพื่อให้ปุ่ม/เมนูบนทำงานครบ -->
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // ซ่อน/โชว์ "เปิดร้านของฉัน" vs "ร้านของฉัน" ให้ถูกบริบท
            if (typeof toggleOpenOrMyShop === 'function') toggleOpenOrMyShop();
        });
    </script>
    <script src="/js/cart-badge.js" defer></script>
</body>

</html>