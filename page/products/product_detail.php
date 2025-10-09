<?php
session_start();

/* --- DB --- */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* --- รับ id --- */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('ไม่พบสินค้า');
}

/* --- web path รูป --- */
$WEB_PREFIX = '/page';
function productMainImageWebPath(array $p): string
{
    global $WEB_PREFIX;
    if (!empty($p['main_image'])) {
        return (strpos($p['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $p['main_image']
            : $p['main_image'];
    }
    $dirFs = realpath(__DIR__ . "/../uploads/products/" . $p['id']);
    if ($dirFs && is_dir($dirFs)) {
        $found = glob($dirFs . "/main_*.*");
        if ($found) return $WEB_PREFIX . "/uploads/products/{$p['id']}/" . basename($found[0]);
    }
    return $WEB_PREFIX . "/img/placeholder.png";
}

/* --- ดึงข้อมูลสินค้า + จังหวัดร้าน --- */
$sql = "SELECT
          p.id, p.name, p.price, p.description, p.main_image, p.created_at,
          s.shop_name, s.province AS shop_province
        FROM products p
        LEFT JOIN shops s ON s.id = p.shop_id
        WHERE p.id = ?
        LIMIT 1";
$stm = $pdo->prepare($sql);
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) {
    http_response_code(404);
    exit('ไม่พบสินค้า');
}

$img  = productMainImageWebPath($p);
$name = htmlspecialchars($p['name'] ?? '');
$price = is_numeric($p['price']) ? '$' . number_format((float)$p['price'], 0) : htmlspecialchars($p['price'] ?? '');
$desc = nl2br(htmlspecialchars($p['description'] ?? ''));
$prov = htmlspecialchars($p['shop_province'] ?: 'ไม่ระบุจังหวัด');
$shop = htmlspecialchars($p['shop_name'] ?: 'ไม่ระบุร้าน');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $name ?> | Thammue</title>

    <!-- สไตล์หลักของเว็บ -->
    <link rel="stylesheet" href="/css/style.css" />
    <!-- สไตล์เฉพาะหน้า Product Detail -->
    <link rel="stylesheet" href="/css/product-detail.css" />
</head>

<body>

    <div class="pd-container">
        <!-- ส่วนบน: รูป + ข้อมูล -->
        <section class="pd-hero">
            <div class="pd-media">
                <img src="<?= $img ?>" alt="<?= $name ?>">
            </div>

            <div class="pd-info">
                <h1 class="pd-title"><?= $name ?></h1>
                <div class="pd-meta">
                    <span class="pd-sold">ขายแล้ว 3 ชิ้น</span>
                </div>

                <div class="pd-price"><?= $price ?></div>

                <div class="pd-ship">
                    <div class="pd-ship-row">
                        <span class="label">การจัดส่ง</span>
                        <span class="value">จะได้รับภายใน 10 ม.ค. - 12 ม.ค.</span>
                    </div>
                    <div class="pd-ship-row">
                        <span class="label">ขนาด</span>
                        <div class="pd-size-group" role="group" aria-label="เลือกขนาด">
                            <button class="size-btn is-active">S</button>
                            <button class="size-btn">M</button>
                            <button class="size-btn">L</button>
                        </div>
                    </div>
                </div>

                <div class="pd-like">🤍 ถูกใจแล้ว 9 คน</div>

                <div class="pd-controls">
                    <div class="qty">
                        <label>จำนวน</label>
                        <select>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="pd-actions">
                        <button class="btn-outline">
                            🛒 เพิ่มไปยังรถเข็น
                        </button>
                        <button class="btn-primary">
                            ซื้อสินค้า
                        </button>
                    </div>
                </div>

                <div class="pd-location">จังหวัด<?= $prov ?> · ร้าน: <?= $shop ?></div>
            </div>
        </section>

        <!-- การ์ดร้านค้า -->
        <section class="pd-shop-card">
            <div class="shop-avatar" aria-hidden="true"><?= mb_substr($shop, 0, 1, 'UTF-8') ?: 'ร' ?></div>
            <div class="shop-main">
                <div class="shop-name"><?= $shop ?></div>
                <div class="shop-actions">
                    <button class="btn-chip">🤝 แชร์</button>
                    <button class="btn-chip">💬 ดูร้านค้า</button>
                </div>
            </div>
        </section>

        <!-- รายละเอียดสินค้า -->
        <section class="pd-section">
            <h2 class="pd-section-title">รายละเอียดสินค้า</h2>
            <div class="pd-desc">
                <?= $desc ?: '—' ?>
            </div>
        </section>
    </div>

    <script>
        // toggle ปุ่มขนาด
        document.querySelectorAll('.size-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
            });
        });
    </script>
</body>

</html>