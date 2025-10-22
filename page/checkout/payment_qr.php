<?php
// /page/checkout/payment_qr.php
session_start();

$PROMPTPAY_MOBILE = '1119902115249'; // ← ใส่เบอร์ PromptPay ของคุณ

$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : null;

// ===== ตัวอย่างคำนวณยอดรวมจาก SESSION =====
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

$ppId  = preg_replace('/\D+/', '', $PROMPTPAY_MOBILE);
$qrUrl = "https://promptpay.io/{$ppId}.png?amount={$amountStr}";
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ชำระเงินด้วยพร้อมเพย์</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/payment_qr.css">
</head>

<body>
    <?php
    // ✅ ใช้ header กลางของเว็บ (เหมือนหน้า checkout)
    $HEADER_NO_CATS = true;                       // ซ่อนแถบหมวดหมู่ ถ้าไม่ต้องการให้โชว์
    include __DIR__ . '/../partials/site-header.php';
    ?>
    <div class="wrap">
        <div class="card">
            <div class="hd">สแกนชำระเงิน (PromptPay)</div>
            <div class="bd">
                <div class="row">
                    <div class="qr-box">
                        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="PromptPay QR Code">
                    </div>
                    <div class="meta">
                        <?php if ($orderId): ?>
                            <div>เลขคำสั่งซื้อ: <b>#<?= htmlspecialchars($orderId) ?></b></div>
                        <?php endif; ?>
                        <!-- <div>ชำระให้: <b><?= htmlspecialchars($ppId) ?></b> (PromptPay)</div> -->

                        <div>ยอดที่ต้องชำระ</div>
                        <div class="amt"><?= number_format($amount, 2) ?> บาท</div>
                        <div class="note">หลังสแกนและชำระเงินแล้ว โปรดกดปุ่ม “ชำระเงินแล้ว” ด้านล่าง</div>
                    </div>
                </div>

                <div class="btns">
                    <?php
                    $back = (($_GET['mode'] ?? '') === 'buy-now')
                        ? '/page/checkout/buy_now.php'
                        : '/page/cart/index.php';
                    ?>
                    <a class="btn btn-ghost" href="/page/cart/index.php">ย้อนกลับ</a>
                    <form action="/page/checkout/confirm_paid.php" method="post" style="display:inline">
                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId ?? '') ?>">
                        <button class="btn btn-primary" type="submit">ชำระเงินแล้ว</button>
                    </form>
                </div>

                <div class="note">
                    เคล็ดลับ: หาก QR ไม่ขึ้น ให้รีเฟรชหน้านี้
                </div>
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
    <script src="/js/header-noti.js"></script>
    <script src="/js/notify-poll.js"></script>
</body>

</html>