<?php
// /page/checkout/order_success.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location:/page/login.html');
    exit;
}
$orderId = (int)($_GET['order_id'] ?? 0);
$method = htmlspecialchars($_GET['m'] ?? '');
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>สั่งซื้อสำเร็จ</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/payment_qr.css" />
    <link rel="stylesheet" href="/css/products.css" />
</head>

<body>
    <?php
    // ✅ ใช้ header กลางของเว็บ (เหมือนหน้า checkout)
    $HEADER_NO_CATS = true;                       // ซ่อนแถบหมวดหมู่ ถ้าไม่ต้องการให้โชว์
    $HEADER_NO_SEARCH = true;
    $HEADER_HIDE_ICONS = true;
    $HEADER_NO_HAMBURGER = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>
    <div class="wrap">
        <div class="card">
            <div class="hd">สั่งซื้อสำเร็จ</div>
            <div class="bd">
                <p>หมายเลขคำสั่งซื้อ: <b>#<?= $orderId ?: '-' ?></b></p>
                <p>
                    <?php if (($method === 'cod')): ?>
                        คำสั่งซื้อของคุณถูกบันทึกแล้ว (เก็บเงินปลายทาง) กรุณารอการจัดส่ง
                    <?php else: ?>
                        ระบบได้รับข้อมูลการชำระเงินแล้ว ขอบคุณที่ใช้บริการ
                    <?php endif; ?>
                </p>
                <a class="btn btn-primary" href="/page/orders/index.php">ดูคำสั่งซื้อของฉัน</a>
            </div>
        </div>
    </div>

    <script src="/js/me.js"></script> <!--ดึงสถานะผู้ใช้-->
    <script src="/js/user-menu.js"></script> <!--จัดการเมนูโปรไฟล์-->
    <script src="/js/store/shop-page.js"></script> <!--จัดการข้อมูลร้านค้า-->
    <script src="/js/productsforsale/get_shop_info.js"></script> <!--ดึงข้อมูลร้านค้า-->


    <script src="/page/js/cart.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>
</body>

</html>