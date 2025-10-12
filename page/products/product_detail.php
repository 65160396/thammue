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

/* === NEW: คืนลิสต์รูปทั้งหมดของสินค้า (เอา main ก่อน ตามด้วยไฟล์อื่นในโฟลเดอร์) === */
function productAllImagesWeb(array $p): array
{
    global $WEB_PREFIX;
    $out = [];

    // main จาก DB
    if (!empty($p['main_image'])) {
        $out[] = (strpos($p['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $p['main_image']
            : $p['main_image'];
    } else {
        // main_* ในโฟลเดอร์
        $dirFs = realpath(__DIR__ . "/../uploads/products/" . $p['id']);
        if ($dirFs && is_dir($dirFs)) {
            $foundMain = glob($dirFs . "/main_*.*");
            if ($foundMain) {
                $out[] = $WEB_PREFIX . "/uploads/products/{$p['id']}/" . basename($foundMain[0]);
            }
        }
    }

    // ไฟล์อื่นๆ ในโฟลเดอร์ (เรียงตามชื่อไฟล์)
    $dirFs = realpath(__DIR__ . "/../uploads/products/" . $p['id']);
    if ($dirFs && is_dir($dirFs)) {
        $all = glob($dirFs . "/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}", GLOB_BRACE);
        natcasesort($all);
        $main = $out[0] ?? null;
        foreach ($all as $abs) {
            $web = $WEB_PREFIX . "/uploads/products/{$p['id']}/" . basename($abs);
            if ($web === $main) continue;
            $out[] = $web;
        }
    }

    if (!$out) $out[] = $WEB_PREFIX . "/img/placeholder.png";
    return array_values($out);
}

/* --- ดึงข้อมูลสินค้า + จังหวัดร้าน --- */
$sql = "SELECT
          p.id,
          p.name,
          p.price,
          p.description,
          p.main_image,
          p.created_at,
          COALESCE(SUM(CASE WHEN oi.status='paid' THEN oi.qty ELSE 0 END), 0) AS sold_count,
          s.shop_name,
          COALESCE(s.province, p.province) AS shop_province
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN shops s       ON s.id       = p.shop_id
        WHERE p.id = ?
        GROUP BY
          p.id, p.name, p.price, p.description, p.main_image, p.created_at,
          s.shop_name, s.province, p.province
        LIMIT 1";

$stm = $pdo->prepare($sql);
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) {
    http_response_code(404);
    exit('ไม่พบสินค้า');
}

$img     = productMainImageWebPath($p);
$gallery = productAllImagesWeb($p);   // <<< ใช้รูปทั้งหมดที่หาได้
$name  = htmlspecialchars($p['name'] ?? '');
$price = is_numeric($p['price']) ? '$' . number_format((float)$p['price'], 0) : htmlspecialchars($p['price'] ?? '');
$desc  = nl2br(htmlspecialchars($p['description'] ?? ''));

$sold  = (int)($p['sold_count'] ?? 0);
$prov  = htmlspecialchars($p['shop_province'] ?: 'ไม่ระบุจังหวัด');
$shop  = htmlspecialchars($p['shop_name'] ?: 'ไม่ระบุร้าน');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $name ?> | Thammue</title>

    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/product-detail.css" />
</head>

<body>
    <?php include __DIR__ . '/../partials/site-header.php'; ?>

    <div class="pd-container">
        <!-- ส่วนบน: รูป + ข้อมูล -->
        <section class="pd-hero">
            <!-- === NEW: แกลเลอรีรูป === -->
            <div class="pd-media">
                <img id="pdMain" src="<?= htmlspecialchars($gallery[0]) ?>" alt="<?= $name ?>">

                <?php if (count($gallery) > 1): ?>
                    <div class="pd-thumbs" role="list">
                        <?php foreach ($gallery as $i => $g): ?>
                            <button
                                class="pd-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                data-index="<?= $i ?>"
                                data-src="<?= htmlspecialchars($g) ?>"
                                aria-label="ดูรูปที่ <?= $i + 1 ?>"
                                role="listitem">
                                <img src="<?= htmlspecialchars($g) ?>" alt="รูปที่ <?= $i + 1 ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pd-info">
                <h1 class="pd-title"><?= $name ?></h1>

                <div class="pd-badges mt-8">
                    <span id="soldPill" class="pd-pill">ขายแล้ว <?= $sold ?> ชิ้น</span>
                </div>

                <div class="pd-price"><?= $price ?></div>

                <div class="fav-row">
                    <button id="likeBtn" class="fav-btn" aria-pressed="false" aria-label="ถูกใจ">🤍</button>
                    <span id="likeCount" class="fav-count">0</span>
                </div>

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
                        <button id="addToCartDetail" class="btn btn-outline" data-id="<?= (int)$p['id'] ?>">
                            🛒 เพิ่มไปยังรถเข็น
                        </button>
                        <button class="btn btn-primary">ซื้อสินค้า</button>
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
                    <button class="btn-chip">แชท</button>
                    <button class="btn-chip">ดูร้านค้า</button>
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

    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>

    <script>
        const ITEM_ID = <?= (int)$p['id'] ?>;
        const likeBtn = document.getElementById('likeBtn');
        const likeCount = document.getElementById('likeCount');
        const soldPill = document.getElementById('soldPill');

        const here = window.location.pathname + window.location.search;
        const toLogin = () => {
            location.href = '/page/login.html?next=' + encodeURIComponent(here);
        };

        async function loadLikeStats() {
            try {
                const res = await fetch(`/page/backend/likes_sale/stats.php?type=product&id=${encodeURIComponent(ITEM_ID)}`, {
                    credentials: 'include',
                    cache: 'no-store'
                });
                if (!res.ok) return;
                const data = await res.json();
                likeCount.textContent = data.count ?? 0;
                likeBtn.textContent = data.liked ? '❤️' : '🤍';
                likeBtn.dataset.liked = data.liked ? '1' : '0';
                likeBtn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                if (typeof data.sold_count !== 'undefined' && soldPill) {
                    soldPill.textContent = `ขายแล้ว ${data.sold_count} ชิ้น`;
                }
            } catch (e) {
                console.error('loadLikeStats error', e);
            }
        }

        async function toggleLike() {
            try {
                const wasLiked = (likeBtn.dataset.liked === '1');
                const res = await fetch('/page/backend/likes_sale/toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        type: 'product',
                        id: ITEM_ID
                    })
                });
                if (res.status === 401) {
                    toLogin();
                    return;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                likeCount.textContent = data.count ?? 0;
                likeBtn.textContent = data.liked ? '❤️' : '🤍';
                likeBtn.dataset.liked = data.liked ? '1' : '0';
                likeBtn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');

                const delta = (data.liked === wasLiked) ? 0 : (data.liked ? 1 : -1);
                window.dispatchEvent(new CustomEvent('favorites:changed', {
                    detail: {
                        delta
                    }
                }));
            } catch (e) {
                console.error('toggleLike error', e);
            }
        }
        likeBtn.addEventListener('click', toggleLike);
        loadLikeStats();
    </script>

    <script src="/js/cart.js"></script>
    <script>
        document.getElementById('addToCartDetail')?.addEventListener('click', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget;
            const id = btn.dataset.id;
            try {
                const res = await fetch('/page/cart/add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        id
                    })
                });
                if (res.status === 401) {
                    location.href = '/page/login.html?next=' + encodeURIComponent(location.pathname + location.search);
                    return;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (data.in_cart) {
                    btn.textContent = 'อยู่ในตะกร้า';
                    btn.classList.add('is-in-cart');
                } else {
                    btn.textContent = '🛒 เพิ่มไปยังรถเข็น';
                    btn.classList.remove('is-in-cart');
                }

                window.dispatchEvent(new CustomEvent('cart:set', {
                    detail: {
                        count: data.cart_count || 0
                    }
                }));
            } catch (err) {
                console.error(err);
                alert('เพิ่ม/ลบจากตะกร้าไม่สำเร็จ');
            }
        });
    </script>

    <!-- === NEW: สคริปต์สลับรูปตาม thumbnail === -->
    <script>
        (function() {
            const main = document.getElementById('pdMain');
            const thumbs = Array.from(document.querySelectorAll('.pd-thumb'));
            if (!main || thumbs.length === 0) return;

            let current = 0;

            function setActive(i) {
                current = i;
                main.src = thumbs[i].dataset.src;
                thumbs.forEach(b => b.classList.remove('is-active'));
                thumbs[i].classList.add('is-active');
            }

            thumbs.forEach((btn, i) => btn.addEventListener('click', () => setActive(i)));

            // รองรับปุ่มลูกศรซ้าย/ขวา
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight' && thumbs.length > 1) setActive((current + 1) % thumbs.length);
                else if (e.key === 'ArrowLeft' && thumbs.length > 1) setActive((current - 1 + thumbs.length) % thumbs.length);
            });
        })();
    </script>

    <script src="/js/cart-badge.js" defer></script>
</body>

</html>