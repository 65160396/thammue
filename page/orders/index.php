<?php
/* =========================================
   [1] ตรวจสิทธิ์ผู้ใช้ (Session/Auth Guard)
   - ถ้าไม่ได้ล็อกอิน → ส่งไปหน้า login
   ========================================= */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* =========================================
   [2] เชื่อมต่อฐานข้อมูล (PDO)
   - ตั้งค่า error/ fetch mode
   ========================================= */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

/* =========================================
   [3] ดึง “รายการคำสั่งซื้อของฉัน”
   - เลือกข้อมูลหลักของออเดอร์ (id, วันที่, สถานะ, ยอดรวม)
   - ดึงชื่อสินค้าชิ้นแรกของออเดอร์เพื่อแสดงเป็นไทเทิล
   - จำกัด 200 รายการ เรียงล่าสุดก่อน
   ========================================= */
$stmt = $pdo->prepare("
  SELECT 
    o.id AS order_id,
    o.created_at,
    o.status,
    o.total_amount,
    (
      SELECT p.name 
      FROM order_items i
      LEFT JOIN products p ON p.id = i.product_id
      WHERE i.order_id = o.id
      ORDER BY i.id ASC
      LIMIT 1
    ) AS first_item_name
  FROM orders o
  WHERE o.user_id = ?
  ORDER BY o.created_at DESC
  LIMIT 200
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <!-- ===============================
       [4] Meta + โหลด CSS ของหน้า Orders
       =============================== -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Orders – คำสั่งซื้อของฉัน</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/orders.css" />
    <link rel="stylesheet" href="/css/products.css" />
</head>

<body class="orders-page">
    <?php
    /* =========================================
     [5] Header กลางของไซต์
     - ปิดแถบหมวด/ค้นหา เพื่อโฟกัสที่ Orders
     ========================================= */
    $HEADER_NO_CATS = true;
    $HEADER_NO_SEARCH = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <div class="wrap">
        <!-- ===============================
         [6] หัวเรื่องหน้าคำสั่งซื้อ
         =============================== -->
        <h1>คำสั่งซื้อของฉัน</h1>

        <!-- ===============================
         [7] แถบตัวกรองสถานะ (Tabs/Filters)
         - all/awaiting/paid/shipped/completed/cancelled
         =============================== -->
        <div class="order-filters" role="tablist">
            <button data-st="all" class="tab active" aria-selected="true">ทั้งหมด</button>
            <button data-st="awaiting" class="tab">รอชำระ</button>
            <button data-st="paid" class="tab">ชำระแล้ว</button>
            <button data-st="shipped" class="tab">จัดส่งแล้ว</button>
            <button data-st="completed" class="tab">สำเร็จ</button>
            <button data-st="cancelled" class="tab">ยกเลิก</button>
        </div>

        <?php if (!$orders): ?>
            <!-- ===============================
           [8] Empty State (ยังไม่มีคำสั่งซื้อ)
           =============================== -->
            <div class="empty">
                <p>ยังไม่มีคำสั่งซื้อ</p>
                <a class="btn" href="/page/main.html">ไปเลือกซื้อสินค้า</a>
            </div>
        <?php else: ?>

            <!-- ===============================
           [9] หัวคอลัมน์ของรายการคำสั่งซื้อ
           =============================== -->
            <div class="order-header-row" role="row">
                <div class="h-name">ชื่อสินค้า</div>
                <div class="h-total">ยอดรวม</div>
                <div class="h-status">สถานะ</div>
                <div class="h-view ta-right">รายละเอียดคำสั่งซื้อ</div>
            </div>

            <!-- ===============================
           [10] รายการคำสั่งซื้อ (List)
           - แสดงชื่อสินค้าแรก + วันเวลา
           - ยอดรวม
           - สถานะ (badge)
           - ลิงก์ไปหน้า view รายละเอียด
           =============================== -->
            <ul id="orderList" class="order-list">
                <?php foreach ($orders as $o): ?>
                    <li class="order-row" data-status="<?= htmlspecialchars($o['status']) ?>">
                        <!-- คอลัมน์ 1: ชื่อ/เลข + วันที่ -->
                        <div class="c-name">
                            <div class="title">
                                <?= htmlspecialchars($o['first_item_name'] ?: 'Order #' . $o['order_id']) ?>
                            </div>
                            <div class="sub"><?= htmlspecialchars($o['created_at']) ?></div>
                        </div>

                        <!-- คอลัมน์ 2: ยอดรวม -->
                        <div class="c-total">฿<?= number_format((float)$o['total_amount'], 2) ?></div>

                        <!-- คอลัมน์ 3: สถานะ (badge) -->
                        <div class="c-status">
                            <span class="badge st-<?= htmlspecialchars($o['status']) ?>">
                                <?= htmlspecialchars($o['status']) ?>
                            </span>
                        </div>

                        <!-- คอลัมน์ 4: ดูรายละเอียดออเดอร์ -->
                        <div class="c-view ta-right">
                            <a class="view-link" href="/page/orders/view.php?id=<?= (int)$o['order_id'] ?>">
                                รายละเอียดคำสั่งซื้อ
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- ===============================
       [11] สคริปต์กรองรายการตามสถานะ (Client-side)
       - groups: map กลุ่มแท็บ → สถานะใน DB
       - applyFilter(): ซ่อน/โชว์ .order-row ด้วย data-status
       =============================== -->
    <script>
        const tabs = document.querySelectorAll('.order-filters .tab');
        const rows = Array.from(document.querySelectorAll('.order-row'));

        const groups = {
            all: null,
            awaiting: ['pending_payment', 'cod_pending'],
            paid: ['paid'],
            shipped: ['shipped'],
            completed: ['completed'],
            cancelled: ['cancelled']
        };

        function applyFilter(key) {
            const allowed = groups[key] || null;
            rows.forEach(row => {
                const s = (row.dataset.status || '').toLowerCase();
                const ok = !allowed || allowed.includes(s);
                row.style.display = ok ? '' : 'none';
            });
        }

        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                tabs.forEach(b => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
                applyFilter(btn.dataset.st);
            });
        });

        // เริ่มต้น: ใช้แท็บที่ active
        const current = document.querySelector('.order-filters .tab.active');
        applyFilter(current ? current.dataset.st : 'all');
    </script>

    <!-- ===============================
       [12] สคริปต์ระบบทั่วไปของเฮดเดอร์/ผู้ใช้/แจ้งเตือน/ร้าน
       - me.js, user-menu.js: สถานะผู้ใช้/เมนูโปรไฟล์
       - header-noti.js / notify-poll.js: ป้ายแจ้งเตือน
       - shop-toggle.js: ปุ่ม “เปิดร้าน/ร้านของฉัน”
       - cart-badge.js: ป้ายจำนวนในตะกร้า
       =============================== -->
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/header-noti.js"></script>
    <script src="/js/notify-poll.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>
    <script src="/js/cart-badge.js" defer></script>

    <!-- ===============================
       [13] Drawer มือถือ + Backdrop
       - เมนูลัดโปรด/ตะกร้า/แชท/โปรไฟล์ (พับได้)
       =============================== -->
    <div class="icons-drawer" id="iconsDrawer" hidden>
        <button class="icons-drawer__close" id="iconsClose" type="button">ปิด</button>

        <a href="/page/favorites/index.php">
            <img src="/img/Icon/heart.png" alt> รายการโปรด
            <span class="badge" id="favBadgeMobile" hidden>0</span>
        </a>

        <a href="/page/cart/index.php">
            <img src="/img/Icon/shopping-cart.png" alt> ตะกร้า
            <span class="badge" id="cartBadgeMobile" hidden>0</span>
        </a>

        <a href="/page/storepage/chat.html">
            <img src="/img/Icon/chat.png" alt> แชท
            <span class="badge" id="chatBadgeMobile" hidden>0</span>
        </a>

        <button id="mobileProfileToggle" class="drawer-acc" aria-expanded="false" type="button">
            <img src="/img/Icon/user.png" alt> โปรไฟล์
            <svg class="chev" viewBox="0 0 20 20">
                <path d="M5.5 7.5l4.5 4 4.5-4" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <div id="mobileAccountMenu" class="drawer-acc-menu" hidden></div>
    </div>
    <div class="icons-backdrop" id="iconsBackdrop" hidden></div>

    <!-- ===============================
       [14] ควบคุมแฮมเบอร์เกอร์/ซิงค์ Drawer
       =============================== -->
    <script src="/js/nav/hamburger.js"></script>
    <script src="/js/nav/drawer-sync.js"></script>
</body>

</html>