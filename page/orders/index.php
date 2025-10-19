<?php
// /page/orders/index.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

// --- DB (ให้แก้ตามที่โปรเจกต์ใช้อยู่) ---
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ดึงคำสั่งซื้อของผู้ใช้
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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Orders – คำสั่งซื้อของฉัน</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/orders.css" />
</head>

<body class="orders-page">
    <?php
    $HEADER_NO_CATS = true;
    $HEADER_NO_SEARCH = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <div class="wrap">
        <h1>คำสั่งซื้อของฉัน</h1>

        <div class="order-filters" role="tablist">
            <button data-st="all" class="tab active" aria-selected="true">ทั้งหมด</button>
            <button data-st="awaiting" class="tab">รอชำระ</button>
            <button data-st="paid" class="tab">ชำระแล้ว</button>
            <button data-st="shipped" class="tab">จัดส่งแล้ว</button>
            <button data-st="completed" class="tab">สำเร็จ</button>
            <button data-st="cancelled" class="tab">ยกเลิก</button>
        </div>


        <?php if (!$orders): ?>
            <div class="empty">
                <p>ยังไม่มีคำสั่งซื้อ</p>
                <a class="btn" href="/page/main.html">ไปเลือกซื้อสินค้า</a>
            </div>
        <?php else: ?>

            <!-- หัวตาราง -->
            <div class="order-header-row" role="row">
                <div class="h-name">
                    ชื่อสินค้า
                </div>
                <div class="h-total">ยอดรวม</div>
                <div class="h-status">สถานะ</div>
                <div class="h-view ta-right">รายละเอียดคำสั่งซื้อ</div>
            </div>

            <ul id="orderList" class="order-list">
                <?php foreach ($orders as $o): ?>
                    <li class="order-row" data-status="<?= htmlspecialchars($o['status']) ?>">
                        <!-- คอลัมน์ 1: ชื่อ/เลขออเดอร์ + วันเวลา -->
                        <div class="c-name">

                            <div class="title">
                                <?= htmlspecialchars($o['first_item_name'] ?: 'Order #' . $o['order_id']) ?>
                            </div>

                            <div class="sub"><?= htmlspecialchars($o['created_at']) ?></div>
                        </div>

                        <!-- คอลัมน์ 2: ยอดรวม -->
                        <div class="c-total">฿<?= number_format((float)$o['total_amount'], 2) ?></div>

                        <!-- คอลัมน์ 3: สถานะ -->
                        <div class="c-status">
                            <span class="badge st-<?= htmlspecialchars($o['status']) ?>">
                                <?= htmlspecialchars($o['status']) ?>
                            </span>
                        </div>

                        <!-- คอลัมน์ 4: ลิงก์ดูรายละเอียด (ข้อความ) -->
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

    <script>
        const tabs = document.querySelectorAll('.order-filters .tab');
        const rows = Array.from(document.querySelectorAll('.order-row'));

        // กลุ่มสถานะที่ตรงกับ DB
        const groups = {
            all: null,
            awaiting: ['pending_payment', 'cod_pending'], // รอจ่ายทุกแบบ
            paid: ['paid'],
            shipped: ['shipped'],
            completed: ['completed'],
            cancelled: ['cancelled'] // ถ้าคุณใช้ชื่ออื่น เช่น auto_cancelled ก็เติมเพิ่มได้
        };

        function applyFilter(key) {
            const allowed = groups[key] || null; // null = ไม่กรอง
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

        // เริ่มต้นด้วยแท็บที่ active
        const current = document.querySelector('.order-filters .tab.active');
        applyFilter(current ? current.dataset.st : 'all');
    </script>


    </script>
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>
    <script src="/js/cart-badge.js" defer></script>
</body>

</html>