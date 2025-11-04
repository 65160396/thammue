<?php
/* =========================
   1) ตรวจสิทธิ์ผู้ใช้ (Session/Auth)
   - ถ้าไม่ล็อกอิน -> เด้งไปหน้า login พร้อม next=/page/favorites/index.php
   ========================= */
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/favorites/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* =========================
   2) เชื่อมต่อฐานข้อมูล (PDO + UTF-8 + error mode)
   ========================= */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* =========================
   3) ดึง “รายการโปรด” ของผู้ใช้ + เช็คสถานะว่าอยู่ในตะกร้าไหม (in_cart)
   - JOIN favorites -> products -> shops
   - LEFT JOIN cart เพื่อตรวจสอบว่าผู้ใช้คนนี้มีสินค้านี้ในตะกร้าหรือยัง
   - เรียงตามเวลาที่กดถูกใจล่าสุด (f.created_at DESC)
   ========================= */
$sql = "SELECT
          p.id, p.name, p.price, p.main_image,
          s.shop_name, s.province,
          IF(c.product_id IS NULL, 0, 1) AS in_cart
        FROM favorites f
        JOIN products p ON p.id = f.item_id AND f.item_type = 'product'
        LEFT JOIN shops s ON s.id = p.shop_id
        LEFT JOIN cart  c ON c.user_id = ? AND c.product_id = p.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute([$userId, $userId]);
$items = $stm->fetchAll();

/* =========================
   4) Helper สำหรับรูปสินค้า + escape HTML
   ========================= */
$WEB_PREFIX = '/page';
function productImg($p)
{
    global $WEB_PREFIX;
    if (!empty($p['main_image'])) {
        return (strpos($p['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $p['main_image']
            : $p['main_image'];
    }
    return $WEB_PREFIX . '/img/placeholder.png';
}
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">

<head>
    <!-- 5) เมตา + CSS หลักของหน้า “รายการโปรด” -->
    <meta charset="utf-8">
    <title>Thammue - รายการโปรด</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/favorites.css" />
    <link rel="stylesheet" href="/css/search.css" />
    <link rel="stylesheet" href="/css/products.css" />
</head>

<body>
    <?php
    /* =========================
       6) ส่วนหัวเว็บไซต์ (Header)
       - ส่ง $HEADER_NO_CATS = true เพื่อซ่อนเมนูหมวดหมู่
       - include ไฟล์ header กลางของไซต์
       ========================= */
    $HEADER_NO_CATS = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <!-- =========================
         7) กล่องผลการค้นหา (เฉพาะหน้า favorites)
         - เริ่มต้น hidden, โชว์เมื่อมีการค้นหา
         - มีปุ่มล้างการค้นหา + พื้นที่ผลลัพธ์ #results
         ========================= -->
    <section id="searchSection" class="recommended-products" hidden>
        <div class="search-results__head">
            <h2>ผลการค้นหา <span id="searchCount"></span></h2>
            <a href="#" id="clearSearch" class="btn btn-primary">ล้างการค้นหา</a>
        </div>
        <div id="results" class="product-grid"></div>
    </section>

    <!-- =========================
         8) หัวข้อหลักของหน้า “รายการโปรด”
         ========================= -->
    <div class="fav-header">
        <h1>รายการโปรด</h1>
    </div>

    <!-- =========================
         9) Grid แสดงสินค้าในรายการโปรด
         - ว่าง: แสดง “ยังไม่มีรายการโปรด”
         - มีรายการ: include การ์ดสินค้า (partials/product_card.php)
         - ส่ง $opts เพื่อควบคุมปุ่ม “ลบออกจากโปรด” ในการ์ด
         ========================= -->
    <div id="favWrap" class="recommended-products" style="padding-top:0">
        <div id="favGrid" class="product-grid">
            <?php if (!$items): ?>
                <div class="empty" style="grid-column:1 / -1;">ยังไม่มีรายการโปรด</div>
            <?php else: ?>
                <?php foreach ($items as $it): ?>
                    <?php $opts = ['showRemoveLike' => true]; ?>
                    <?php include __DIR__ . '/../partials/product_card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- =========================
         10) สคริปต์พื้นฐานบัญชีผู้ใช้ + เมนูโปรไฟล์
         ========================= -->
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>

    <!-- =========================
         11) Badge ตะกร้า (ให้ทำงานทุกหน้า)
         ========================= -->
    <script src="/js/cart-badge.js" defer></script>

    <!-- =========================
         12) ลบออกจาก “รายการโปรด” (ฝั่งหน้า Favorites)
         - ดักคลิกปุ่ม .remove-like
         - เรียก /page/backend/likes_sale/toggle.php แบบ JSON
         - อัปเดต DOM + ลด badge รายการโปรดแบบทันที
         - ถ้าเหลือ 0 ชิ้น แสดงข้อความว่าง
         ========================= -->
    <script>
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.remove-like');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            const id = btn.dataset.id;
            try {
                const res = await fetch('/page/backend/likes_sale/toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        type: 'product',
                        id
                    })
                });
                if (res.status === 401) {
                    location.href = '/page/login.html?next=' + encodeURIComponent(location.pathname);
                    return;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (!data.liked) {
                    const card = btn.closest('.product-card');
                    card?.remove();

                    // แจ้งระบบให้ลด badge โปรด
                    window.dispatchEvent(new CustomEvent('favorites:changed', {
                        detail: {
                            delta: -1
                        }
                    }));

                    // ถ้าไม่มีการ์ดเหลือ แสดง empty state
                    const grid = document.getElementById('favGrid');
                    if (grid && !grid.querySelector('.product-card')) {
                        const empty = document.createElement('div');
                        empty.className = 'empty';
                        empty.style.gridColumn = '1 / -1';
                        empty.textContent = 'ยังไม่มีรายการโปรด';
                        grid.appendChild(empty);
                    }
                }
            } catch (err) {
                console.error(err);
                alert('เอาออกไม่สำเร็จ');
            }
        });
    </script>

    <!-- =========================
         13) ปุ่มเพิ่ม/ลบตะกร้า (สคริปต์ใช้งานได้ทุก list)
         ========================= -->
    <script src="/js/cart.js"></script>

    <!-- =========================
         14) ปุ่ม “เปิดร้านของฉัน/เปิดร้านค้า” + init หลัง DOM พร้อม
         ========================= -->
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>

    <!-- =========================
         15) ระบบค้นหาแบบเฉพาะในหน้า Favorites
         - เปลี่ยน endpoint เป็น /backend/search/search_favorites.php
         - กำหนดจำนวน/ดีบาวน์/จดจำคำล่าสุด
         ========================= -->
    <script src="/js/search/search.js"></script>
    <script>
        Search.init({
            input: "#q",
            button: "#btnSearch",
            results: "#results",
            endpoint: "/page/backend/search/search_favorites.php",
            per: 24,
            sort: "relevance",
            minLength: 1,
            debounceMs: 300,
            rememberLast: true
        });
    </script>

    <!-- =========================
         16) Drawer ไอคอน (มือถือ) + Backdrop
         - เมนูลัด: โปรด/ตะกร้า/แชท/โปรไฟล์พับได้
         ========================= -->
    <div class="icons-drawer" id="iconsDrawer" hidden>
        <button class="icons-drawer__close" id="iconsClose" type="button">ปิด</button>

        <a href="/page/favorites/index.php">
            <img src="/img/Icon/heart.png" alt=""> รายการโปรด
            <span class="badge" id="favBadgeMobile" hidden>0</span>
        </a>

        <a href="/page/cart/index.php">
            <img src="/img/Icon/shopping-cart.png" alt=""> ตะกร้า
            <span class="badge" id="cartBadgeMobile" hidden>0</span>
        </a>

        <a href="/page/storepage/chat.html">
            <img src="/img/Icon/chat.png" alt=""> แชท
            <span class="badge" id="chatBadgeMobile" hidden>0</span>
        </a>

        <!-- โปรไฟล์แบบพับได้ -->
        <button id="mobileProfileToggle" class="drawer-acc" aria-expanded="false" type="button">
            <img src="/img/Icon/user.png" alt=""> โปรไฟล์
            <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M5.5 7.5l4.5 4 4.5-4" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <div id="mobileAccountMenu" class="drawer-acc-menu" hidden></div>
    </div>
    <div class="icons-backdrop" id="iconsBackdrop" hidden></div>

    <!-- =========================
         17) สคริปต์ควบคุมเมนูแฮมเบอร์เกอร์ + ซิงก์ Drawer/Badge
         ========================= -->
    <script src="/js/nav/hamburger.js"></script>
    <script src="/js/nav/drawer-sync.js"></script>
</body>

</html>