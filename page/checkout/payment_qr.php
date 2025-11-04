<?php
// ==========================================
// [1] เริ่มเซสชัน + ตั้งค่า PromptPay และอ่าน order_id
// - ใช้ $PROMPTPAY_MOBILE เป็นหมายเลขสำหรับออก QR
// - รับเลขคำสั่งซื้อ (ถ้ามี) ผ่าน $_GET['order_id']
// ==========================================
session_start();
$PROMPTPAY_MOBILE = '1119902115249';
$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : null;

// ==========================================
// [2] คำนวณยอดชำระ ($amount)
// - พยายามดึงจาก SESSION: checkout_total > cart_items
// - สร้างสตริงจำนวนเงิน 2 ตำแหน่งทศนิยมสำหรับ QR
// ==========================================
$amount = 0.00;
if (isset($_SESSION['checkout_total'])) {
    $amount = floatval($_SESSION['checkout_total']);
} elseif (isset($_SESSION['cart_items']) && is_array($_SESSION['cart_items'])) {
    foreach ($_SESSION['cart_items'] as $it) {
        $price = isset($it['price']) ? floatval($it['price']) : 0;
        $qty   = isset($it['qty']) ? intval($it['qty']) : 0;
        $amount += $price * $qty;
    }
}
if ($amount < 0) $amount = 0.00;
$amountStr = number_format($amount, 2, '.', '');

// ==========================================
// [3] เตรียม URL QR พร้อมเพย์ (ผ่าน promptpay.io)
// - แปลงเบอร์ให้เหลือเฉพาะตัวเลข
// - ประกอบลิงก์ PNG พร้อมพารามิเตอร์ amount
// ==========================================
$ppId  = preg_replace('/\D+/', '', $PROMPTPAY_MOBILE);
$qrUrl = "https://promptpay.io/{$ppId}.png?amount={$amountStr}";
?>
<!doctype html>
<html lang="th">

<head>
    <!-- ===============================
         [4] Meta + โหลด CSS ของหน้านี้
         =============================== -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ชำระเงินด้วยพร้อมเพย์</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/payment_qr.css">
    <link rel="stylesheet" href="/css/products.css" />
</head>

<body>
    <?php
    // ==========================================
    // [5] ส่วนหัว (Header ส่วนกลางของเว็บ)
    // - ปิดบางองค์ประกอบ: หมวดหมู่, ค้นหา, ไอคอน, แฮมเบอร์เกอร์
    // - ใช้ header รูปแบบเดียวกับหน้า checkout
    // ==========================================
    $HEADER_NO_CATS = true;
    $HEADER_NO_SEARCH = true;
    $HEADER_HIDE_ICONS = true;
    $HEADER_NO_HAMBURGER = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

    <div class="wrap">
        <div class="card">
            <!-- ===============================
                 [6] หัวข้อการชำระเงินแบบ PromptPay
                 =============================== -->
            <div class="hd">สแกนชำระเงิน (PromptPay)</div>

            <div class="bd">
                <div class="row">
                    <!-- ===============================
                         [7] กล่องแสดง QR พร้อมเพย์
                         - ดึงรูปจาก $qrUrl
                         =============================== -->
                    <div class="qr-box">
                        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="PromptPay QR Code">
                    </div>

                    <!-- ===============================
                         [8] ข้อมูลประกอบการชำระเงิน
                         - แสดงเลขคำสั่งซื้อ (ถ้ามี)
                         - แสดงยอดชำระ
                         - ใส่โน้ตแนะนำขั้นตอนหลังชำระ
                         =============================== -->
                    <div class="meta">
                        <?php if ($orderId): ?>
                            <div>เลขคำสั่งซื้อ: <b>#<?= htmlspecialchars($orderId) ?></b></div>
                        <?php endif; ?>

                        <div>ยอดที่ต้องชำระ</div>
                        <div class="amt"><?= number_format($amount, 2) ?> บาท</div>
                        <div class="note">หลังสแกนและชำระเงินแล้ว โปรดกดปุ่ม “ชำระเงินแล้ว” ด้านล่าง</div>
                    </div>
                </div>

                <!-- ===============================
                     [9] ปุ่มการทำงาน
                     - ย้อนกลับไปตะกร้า
                     - ส่งฟอร์มยืนยันว่า “ชำระเงินแล้ว”
                     =============================== -->
                <div class="btns">
                    <?php
                    $back = (($_GET['mode'] ?? '') === 'buy-now')
                        ? '/page/checkout/buy_now.php'
                        : '/page/cart/index.php';
                    ?>
                    <a class="btn btn-ghost" href="/page/cart/index.php">ย้อนกลับ</a>

                    <!-- ส่งไป confirm_paid.php เพื่อติดธงชำระแล้วตาม order_id -->
                    <form action="/page/checkout/confirm_paid.php" method="post" style="display:inline">
                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId ?? '') ?>">
                        <button class="btn btn-primary" type="submit">ชำระเงินแล้ว</button>
                    </form>
                </div>

                <!-- ===============================
                     [10] หมายเหตุ/คำแนะนำเพิ่มเติม
                     =============================== -->
                <div class="note">
                    เคล็ดลับ: หาก QR ไม่ขึ้น ให้รีเฟรชหน้านี้
                </div>
            </div>
        </div>
    </div>

    <!-- ===============================
         [11] สคริปต์พื้นฐานของระบบ (ผู้ใช้/ร้าน/แจ้งเตือน ฯลฯ)
         - ดึงสถานะผู้ใช้, จัดการเมนูโปรไฟล์
         - สคริปต์ร้านค้า/สินค้า (ถ้า header ต้องใช้)
         - ปรับปุ่มเปิดร้าน/ร้านของฉันหลัง DOM พร้อม
         =============================== -->
    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/store/shop-page.js"></script>
    <script src="/js/productsforsale/get_shop_info.js"></script>

    <!-- (ถ้ามีการใช้ cart.js ใน header ส่วนกลาง) -->
    <script src="/page/js/cart.js"></script>

    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>

    <!-- แจ้งเตือนส่วนหัว (badge/โพลแจ้งเตือน) -->
    <script src="/js/header-noti.js"></script>
    <script src="/js/notify-poll.js"></script>
</body>

</html>