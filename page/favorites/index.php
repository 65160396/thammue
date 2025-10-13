<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/favorites/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* DB */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* ดึงสินค้าที่ถูกใจ + สถานะในตะกร้า (in_cart) */
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
$stm->execute([$userId, $userId]); // ส่ง $userId สองครั้งให้ JOIN cart ทำงาน
$items = $stm->fetchAll();

/* helper */
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
    <meta charset="utf-8">
    <title>รายการโปรด | Thammue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/products.css" />
    <link rel="stylesheet" href="/css/favorites.css" />
</head>

<body>
    <?php
    // ให้ header แสดงเฉพาะแถบบน (โลโก้/ค้นหา/ไอคอน) และซ่อนเมนูหมวดหมู่
    $HEADER_NO_CATS = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <div class="fav-header">
        <h1>รายการโปรด</h1>
    </div>

    <div class="recommended-products" style="padding-top:0">
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

    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script> <!-- เมนูโปรไฟล์ dropdown -->

    <!-- ให้ badge ทั้งสองทำงานทุกหน้า -->
    <script src="/js/cart-badge.js" defer></script>

    <!-- เอาออกจากรายการโปรด -->
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
                    // ลบการ์ดออกจาก DOM
                    const card = btn.closest('.product-card');
                    card?.remove();

                    // ลด badge รายการโปรดทันที
                    window.dispatchEvent(new CustomEvent('favorites:changed', {
                        detail: {
                            delta: -1
                        }
                    }));

                    // ถ้าเหลือ 0 ชิ้น แสดงกล่องว่าง
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

    <!-- ปุ่มเพิ่ม/ลบตะกร้า (ใช้ได้ทุกหน้า list) -->
    <script src="/page/js/cart.js"></script>

    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>
</body>

</html>