<?php
session_start();

//$userId = $_SESSION['user_id'] ?? null;   // ชื่อคีย์ตามระบบล็อกอินของคุณ
//if (!$userId) {
// พารามิเตอร์ next เพื่อกลับมาหน้ารายละเอียดเดิมหลังล็อกอินเสร็จ
//$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// $next = "/page/products/product_detail.php?id=" . $id;
//header("Location: /page/login.html?next=" . rawurlencode($next));
//exit;
//}


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
          p.id,
          p.name,
          p.price,
          p.description,
          p.main_image,
          p.created_at,
          -- รวมยอดขายเฉพาะแถวที่จ่ายเงินแล้ว
          COALESCE(SUM(CASE WHEN oi.status='paid' THEN oi.qty ELSE 0 END), 0) AS sold_count,
          s.shop_name,
          -- ถ้าจังหวัดร้านว่าง ให้ใช้จังหวัดที่บันทึกกับสินค้าแทน
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

$img   = productMainImageWebPath($p);
$name  = htmlspecialchars($p['name'] ?? '');
$price = is_numeric($p['price']) ? '$' . number_format((float)$p['price'], 0) : htmlspecialchars($p['price'] ?? '');
$desc  = nl2br(htmlspecialchars($p['description'] ?? ''));

$sold  = (int)($p['sold_count'] ?? 0);                     // << ใช้ตัวนี้แสดงป้าย “ขายแล้ว … ชิ้น”
$prov  = htmlspecialchars($p['shop_province'] ?: 'ไม่ระบุจังหวัด');
$shop  = htmlspecialchars($p['shop_name'] ?: 'ไม่ระบุร้าน');
//$shop  = htmlspecialchars($p['shop_name'] ?? 'ไม่ระบุร้าน');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $name ?> | Thammue</title>

    <!-- สไตล์หลักของเว็บ -->
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/product-detail.css" />

</head>

<body>
    <?php include __DIR__ . '/../partials/site-header.php'; ?>

    <div class="pd-container">
        <!-- ส่วนบน: รูป + ข้อมูล -->
        <section class="pd-hero">
            <div class="pd-media">
                <img src="<?= $img ?>" alt="<?= $name ?>">
            </div>

            <div class="pd-info">
                <h1 class="pd-title"><?= $name ?></h1>

                <!-- ป้ายสรุป -->
                <div class="pd-badges mt-8">
                    <span id="soldPill" class="pd-pill">ขายแล้ว <?= $sold ?> ชิ้น</span>
                </div>


                <div class="pd-price"><?= $price ?></div>

                <!-- ลบก้อน .pd-ship ทั้งหมดออก ถ้าไม่ได้ใช้ -->

                <!-- แถวหัวใจ (เปลี่ยนคลาสให้ตรง CSS) -->
                <div class="fav-row">
                    <button id="likeBtn" class="fav-btn" aria-pressed="false" aria-label="ถูกใจ">🤍</button>
                    <span id="likeCount" class="fav-count">0</span>
                </div>


                <!-- ปุ่ม -->
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
                        <button class="btn-outline">🛒 เพิ่มไปยังรถเข็น</button>
                        <button class="btn-primary">ซื้อสินค้า</button>
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

    <script>
        // toggle ปุ่มขนาด
        document.querySelectorAll('.size-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
            });
        });
    </script>
    <script src="/js/me.js"></script> <!--ดึงสถานะผู้ใช้-->
    <script src="/js/user-menu.js"></script> <!--จัดการเมนูโปรไฟล์-->

    <script>
        const ITEM_ID = <?= (int)$p['id'] ?>;

        const likeBtn = document.getElementById('likeBtn');
        const likeCount = document.getElementById('likeCount');
        const soldPill = document.getElementById('soldPill'); // ถ้าจะอัปเดตแบบไดนามิก

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
                const data = await res.json(); // { count, liked, sold_count? }
                likeCount.textContent = data.count ?? 0;
                likeBtn.textContent = data.liked ? '❤️' : '🤍';
                likeBtn.dataset.liked = data.liked ? '1' : '0';
                likeBtn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');

                // ถ้า API ส่ง sold_count มาด้วย จะอัปเดตป้ายได้ (ไม่บังคับ)
                if (typeof data.sold_count !== 'undefined' && soldPill) {
                    soldPill.textContent = `ขายแล้ว ${data.sold_count} ชิ้น`;
                }
            } catch (e) {
                console.error('loadLikeStats error', e);
            }
        }

        async function toggleLike() {
            try {
                const wasLiked = (likeBtn.dataset.liked === '1'); // สถานะเดิมก่อนยิง API

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

                const data = await res.json(); // { count, liked }

                // อัปเดต UI ภายในหน้ารายละเอียด
                likeCount.textContent = data.count ?? 0;
                likeBtn.textContent = data.liked ? '❤️' : '🤍';
                likeBtn.dataset.liked = data.liked ? '1' : '0';
                likeBtn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');

                // ===== ส่งสัญญาณให้ header ปรับ badge =====
                // คำนวณความต่างจากสถานะเดิม -> +1/-1/0
                const delta = (data.liked === wasLiked) ? 0 : (data.liked ? 1 : -1);
                window.dispatchEvent(new CustomEvent('favorites:changed', {
                    detail: {
                        delta
                    } // fav-badge.js จะเพิ่ม/ลดเลขจาก delta
                }));
            } catch (e) {
                console.error('toggleLike error', e);
            }
        }

        likeBtn.addEventListener('click', toggleLike);
        loadLikeStats();
    </script>


</body>

</html>