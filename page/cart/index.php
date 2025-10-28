<?php
// /page/cart/index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/cart/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ดึงรายการตะกร้า + ข้อมูลสินค้า
$sql = "SELECT c.id AS cart_id,  c.product_id, c.quantity, p.name, p.price, p.main_image
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute([$userId]);
$items = $stm->fetchAll();

// helper
$WEB_PREFIX = '/page';
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function imgPath($row)
{
    global $WEB_PREFIX;
    if (!empty($row['main_image'])) {
        return (strpos($row['main_image'], '/uploads/') === 0) ? $WEB_PREFIX . $row['main_image'] : $row['main_image'];
    }
    return $WEB_PREFIX . '/img/placeholder.png';
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <title>Thammue - ตะกร้าสินค้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/cart.css" />
    <link rel="stylesheet" href="/css/products.css" />
</head>

<body class="cart-page">
    <?php
    // ให้ header แสดงเฉพาะแถบบน (โลโก้/ค้นหา/ไอคอน) และซ่อนเมนูหมวดหมู่
    $HEADER_NO_CATS = true;
    $HEADER_NO_SEARCH = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <div class="cart-wrap">
        <h1>ตะกร้าสินค้า</h1>

        <?php if (!$items): ?>
            <p>ตะกร้าว่างเปล่า</p>
        <?php else: ?>

            <!-- แถบเลือกทั้งหมด -->
            <div class="cart-toolbar">
                <label class="cart-check">
                    <input type="checkbox" id="checkAll"> เลือกทั้งหมด
                </label>
            </div>

            <div class="cart-list">
                <?php foreach ($items as $r):
                    $qty   = max(1, (int)$r['quantity']);
                    $price = is_numeric($r['price']) ? (float)$r['price'] : 0;
                    $line  = $price * $qty;
                ?>
                    <div class="cart-item" data-id="<?= (int)$r['product_id'] ?>">
                        <!-- เช็คบ็อกซ์เลือกรายการ -->
                        <label class="cart-check">
                            <input
                                type="checkbox"
                                class="ci"
                                name="selected[]"
                                value="<?= (int)$r['cart_id'] ?>"
                                form="payForm"
                                data-price="<?= number_format($line, 2, '.', '') ?>">
                        </label>


                        <img src="<?= imgPath($r) ?>" alt="<?= h($r['name']) ?>">

                        <div class="cart-info">
                            <div class="cart-title"><?= h($r['name']) ?></div>

                            <!-- คอนโทรลจำนวน -->
                            <div class="qty">
                                <button class="qty-btn minus" type="button">−</button>
                                <input class="qty-input" type="text" value="<?= (int)$qty ?>" inputmode="numeric">
                                <button class="qty-btn plus" type="button">+</button>
                            </div>
                        </div>

                        <div class="cart-price">
                            $<span class="line-total"><?= number_format($line, 2) ?></span>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

            <!-- สรุป + ปุ่มชำระเงิน -->
            <div class="cart-summary">
                <div><strong>รวมที่เลือก:</strong> <span id="sum">$0.00</span></div>

                <!-- ฟอร์มส่งเฉพาะรายการที่เลือก -->
                <form id="payForm" action="/page/checkout/checkout.php" method="post">
                    <button id="payBtn" class="cart-btn-primary is-attention" disabled>ชำระเงิน</button>
                </form>

            </div>

        <?php endif; ?>
    </div>

    <script>
        // อัปเดต badge จำนวนชิ้นใน cart เมื่อเข้าหน้านี้
        window.dispatchEvent(new CustomEvent('cart:set', {
            detail: {
                count: <?= count($items) ?>
            }
        }));

        // ===== เลือกบางชิ้น/ทั้งหมด + คำนวณยอดรวมที่เลือก =====
        const fmt = n => '$' + Number(n).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        const sumEl = document.getElementById('sum');
        const payBtn = document.getElementById('payBtn');
        const allBox = document.getElementById('checkAll');
        const BOX_SEL = '.ci';

        function recalc() {
            let total = 0;
            const picked = [];
            const boxes = Array.from(document.querySelectorAll(BOX_SEL)); // query สดทุกครั้ง
            boxes.forEach(b => {
                if (b.checked) {
                    total += parseFloat(b.dataset.price || 0);
                    picked.push(b.value); // ใช้ value = cart_id
                }
            });
            sumEl.textContent = fmt(total);
            payBtn.disabled = picked.length === 0;
            if (allBox) allBox.checked = picked.length > 0 && picked.length === boxes.length;
        }

        // change รายการย่อย
        document.querySelectorAll(BOX_SEL).forEach(b => b.addEventListener('change', recalc));

        // check-all
        allBox?.addEventListener('change', () => {
            document.querySelectorAll(BOX_SEL).forEach(b => b.checked = allBox.checked);
            recalc();
        });

        // กันกดส่งโดยไม่ได้เลือก
        document.getElementById('payForm')?.addEventListener('submit', e => {
            const anyChecked = Array.from(document.querySelectorAll(BOX_SEL)).some(b => b.checked);
            if (!anyChecked) e.preventDefault();
        });

        recalc(); // เริ่มต้น
    </script>


    <script src="/js/fav-badge.js" defer></script>
    <script src="/js/cart-badge.js" defer></script>
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script> <!-- เมนูโปรไฟล์ dropdown -->

    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>

    <script>
        const list = document.querySelector('.cart-list');
        const money = n => Number(n).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // อัปเดต badge ที่ header
        function setCartBadge(n) {
            window.dispatchEvent(new CustomEvent('cart:set', {
                detail: {
                    count: n
                }
            }));
        }

        // ยิง API ไปแก้จำนวนใน DB + อัปเดต UI แถวนี้ + recalculation
        async function updateQty(productId, qty, rowEl) {
            try {
                const res = await fetch('/page/cart/update_qty.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        id: productId,
                        qty
                    })
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json(); // {qty, line_total, cart_count}

                if (data.qty === 0) {
                    rowEl.remove();
                } else {
                    rowEl.querySelector('.qty-input').value = data.qty;
                    rowEl.querySelector('.line-total').textContent = money(data.line_total);
                    const cb = rowEl.querySelector('.ci');
                    if (cb) cb.dataset.price = data.line_total.toFixed(2);
                }
                setCartBadge(data.cart_count);
                recalc();
            } catch (e) {
                console.error(e);
                alert('อัปเดตจำนวนไม่สำเร็จ');
            }
        }

        // คลิก +/–
        list?.addEventListener('click', e => {
            const btn = e.target.closest('.qty-btn');
            if (!btn) return;

            const row = e.target.closest('.cart-item');
            const id = Number(row.dataset.id);
            const input = row.querySelector('.qty-input');

            let qty = parseInt(input.value || '1', 10) || 1;
            if (btn.classList.contains('plus')) qty++;
            if (btn.classList.contains('minus')) qty = Math.max(0, qty - 1); // 0 = ลบรายการ

            updateQty(id, qty, row);
        });

        // พิมพ์จำนวนเอง -> หลุดโฟกัสหรือกด Enter ให้ส่งอัปเดต
        list?.addEventListener('change', e => {
            const inp = e.target.closest('.qty-input');
            if (!inp) return;

            const row = e.target.closest('.cart-item');
            const id = Number(row.dataset.id);
            let qty = parseInt(inp.value || '1', 10);
            if (isNaN(qty) || qty < 0) qty = 1;

            updateQty(id, qty, row);
        });
    </script>

    <!-- ===== Drawer มือถือ ===== -->
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

    <!-- โหลดหลังจากมี Drawer แล้ว -->
    <script src="/js/nav/hamburger.js"></script>
    <script src="/js/nav/drawer-sync.js"></script>




</body>

</html>