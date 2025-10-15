<?php
// /page/orders/view.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($n)
{
    return number_format((float)$n, 2);
}
$WEB_PREFIX = '/page';
function productImageWeb(int $pid, ?string $imagePath): string
{
    global $WEB_PREFIX;
    if ($imagePath && preg_match('~^https?://~i', $imagePath)) return $imagePath;
    if ($imagePath && strpos($imagePath, '/uploads/') === 0) return $WEB_PREFIX . $imagePath;
    if ($imagePath && strpos($imagePath, '/') !== false) {
        $imagePath = ltrim($imagePath, '/');
        return $WEB_PREFIX . '/' . $imagePath;
    }
    if ($imagePath) return $WEB_PREFIX . "/uploads/products/{$pid}/" . $imagePath;
    $dirFs = realpath(__DIR__ . "/../uploads/products/" . $pid);
    if ($dirFs && is_dir($dirFs)) {
        $any = glob($dirFs . "/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}", GLOB_BRACE);
        if ($any) return $WEB_PREFIX . "/uploads/products/{$pid}/" . basename($any[0]);
    }
    return $WEB_PREFIX . "/img/placeholder.png";
}

// รับ id
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order id');
}

// ออเดอร์หลัก
$stmt = $pdo->prepare("
  SELECT 
    o.id AS order_id,
    o.user_id,
    o.created_at,
    o.status,
    o.total_amount,
    o.pay_method,
    o.paid_at,
    o.order_code,
    o.payment_deadline
  FROM orders o
  WHERE o.id = ? AND o.user_id = ?
  LIMIT 1
");

$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found or no permission.');
}

$order['payment_method'] = $order['pay_method'] ?? '';

// ใช้รหัสจากคอลัมน์ order_code โดยตรง (ฐานข้อมูลบังคับ unique แล้ว)
$order_code = $order['order_code'] ?? '';


// ที่อยู่จาก user_profiles
$addrStmt = $pdo->prepare("
  SELECT TRIM(CONCAT(up.first_name,' ',up.last_name)) AS ship_name,
         up.phone AS ship_phone, up.addr_line AS ship_addr_line,
         up.addr_subdistrict AS ship_subdistrict, up.addr_district AS ship_district,
         up.addr_province AS ship_province, up.addr_postcode AS ship_postcode
  FROM user_profiles up WHERE up.user_id=? LIMIT 1
");
$addrStmt->execute([$userId]);
$addr = $addrStmt->fetch() ?: [];
foreach (['ship_name', 'ship_phone', 'ship_addr_line', 'ship_subdistrict', 'ship_district', 'ship_province', 'ship_postcode'] as $k) {
    $order[$k] = $addr[$k] ?? '';
}

// ไอเท็ม + รูป
$it = $pdo->prepare("
  SELECT i.product_id, i.qty, i.price, p.name AS product_name,
         (SELECT pi.image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
  FROM order_items i
  INNER JOIN products p ON p.id=i.product_id
  WHERE i.order_id=? ORDER BY i.id ASC
");
$it->execute([$orderId]);
$items = $it->fetchAll();
foreach ($items as &$r) {
    $r['image_web'] = productImageWeb((int)$r['product_id'], $r['image_path'] ?? null);
}
unset($r);

// สิทธิ์ปุ่ม
$canPay = in_array($order['status'], ['pending_payment', 'cod_pending'])
    && empty($order['paid_at'])
    && (!empty($order['payment_deadline']) ? strtotime($order['payment_deadline']) > time() : true);
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Order #<?= h($order['order_id']) ?> – รายละเอียดคำสั่งซื้อ</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/orders.css" />
    <link rel="stylesheet" href="/css/order-view.css" />
</head>

<body class="order-view-page">
    <?php $HEADER_NO_CATS = true;
    include __DIR__ . '/../partials/site-header.php'; ?>
    <div class="wrap">

        <!-- สรุป -->
        <section class="card order-summary">
            <div class="left">
                <div class="oid">Order #<?= h($order['order_id']) ?> <span class="muted">| รหัส: <?= h($order_code) ?></span></div>
                <div class="meta">
                    <span class="label">สั่งซื้อเมื่อ:</span> <?= h($order['created_at']) ?>
                    <?php if (!empty($order['paid_at'])): ?> • <span class="label">ชำระเมื่อ:</span> <?= h($order['paid_at']) ?><?php endif; ?>
                </div>
            </div>
            <div class="right">
                <span class="badge st-<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
                <div class="total">ยอดรวม <strong>฿<?= money($order['total_amount']) ?></strong></div>
                <div class="paymethod">วิธีชำระ: <?= h($order['payment_method'] ?: '—') ?></div>
            </div>
        </section>

        <!-- รายการสินค้า -->
        <section class="card order-items">
            <h2>รายการสินค้า</h2>
            <?php if (!$items): ?>
                <p class="muted">ไม่มีรายการสินค้าในออเดอร์นี้</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($items as $row): ?>
                        <li class="item">
                            <img class="thumb" src="<?= h($row['image_web']) ?>" alt="">
                            <div class="info">
                                <div class="name"><?= h($row['product_name'] ?: ('สินค้า #' . (int)$row['product_id'])) ?></div>
                                <div class="meta">
                                    จำนวน <strong><?= (int)$row['qty'] ?></strong> •
                                    ราคา/ชิ้น ฿<?= money($row['price']) ?> •
                                    รวม ฿<?= money($row['qty'] * $row['price']) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- ที่อยู่ -->
        <section class="card shipping">
            <h2>ที่อยู่จัดส่ง</h2>
            <div class="addr">
                <div><strong><?= h($order['ship_name'] ?: '—') ?></strong> • <?= h($order['ship_phone'] ?: '—') ?></div>
                <div><?= h($order['ship_addr_line'] ?: '—') ?></div>
                <div><?= h($order['ship_subdistrict'] ?: '') ?> <?= h($order['ship_district'] ?: '') ?> <?= h($order['ship_province'] ?: '') ?> <?= h($order['ship_postcode'] ?: '') ?></div>
            </div>
        </section>

        <!-- การจัดส่ง -->
        <section class="card tracking">
            <h2>การจัดส่ง</h2>
            <?php if (!empty($order['tracking_no'])): ?>
                <div>บริษัทขนส่ง: <strong><?= h($order['tracking_company'] ?: '—') ?></strong></div>
                <div>หมายเลขพัสดุ: <strong><?= h($order['tracking_no']) ?></strong></div>
            <?php else: ?>
                <p class="muted">ยังไม่จัดส่ง</p>
            <?php endif; ?>
        </section>



        <?php
        $canPay = in_array($order['status'], ['pending_payment', 'cod_pending'])
            && empty($order['paid_at'])
            && (!empty($order['payment_deadline']) ? strtotime($order['payment_deadline']) > time() : true);

        $canCancel = in_array($order['status'], ['pending_payment', 'cod_pending']) && empty($order['paid_at']);
        ?>

        <?php if (
            !empty($order['payment_deadline']) &&
            empty($order['paid_at']) &&
            in_array($order['status'], ['pending_payment', 'cod_pending'])
        ): ?>
            <div class="muted" style="margin-top:8px;">
                โปรดชำระภายใน:
                <span id="countdown" style="margin-left:8px;font-weight:600;"></span>
            </div>

            <script>
                (function() {
                    const end = new Date("<?= h($order['payment_deadline']) ?>").getTime();
                    const el = document.getElementById('countdown');

                    function fmt(n) {
                        return String(n).padStart(2, '0');
                    }

                    function tick() {
                        const now = Date.now();
                        let diff = Math.floor((end - now) / 1000);
                        if (diff <= 0) {
                            el.textContent = "หมดเวลาแล้ว";
                            clearInterval(t);
                            return;
                        }

                        const d = Math.floor(diff / 86400);
                        diff %= 86400;
                        const h = Math.floor(diff / 3600);
                        diff %= 3600;
                        const m = Math.floor(diff / 60);
                        const s = diff % 60;

                        // แสดงเฉพาะเวลานับถอยหลัง
                        el.textContent = (d > 0) ?
                            `${d} วัน ${fmt(h)}:${fmt(m)}:${fmt(s)}` :
                            `${fmt(h)}:${fmt(m)}:${fmt(s)}`;
                    }

                    const t = setInterval(tick, 1000);
                    tick();
                })();
            </script>
        <?php endif; ?>

        <!-- ปุ่ม -->
        <section class="actions">
            <a class="btn back same-w" href="/page/orders">ย้อนกลับ</a>

            <?php if ($canPay): ?>
                <a class="btn btn-dark same-w"
                    href="/page/checkout/payment_qr.php?order_id=<?= (int)$order['order_id'] ?>">ชำระเงินด้วย QR</a>
            <?php else: ?>
                <a class="btn btn-dark same-w" href="/">ไปหน้าหลัก</a>
            <?php endif; ?>

            <?php if ($canCancel): ?>
                <form method="post"
                    action="/page/orders/cancel_order.php"
                    style="margin-left:auto"
                    onsubmit="return confirm('ยืนยันยกเลิกคำสั่งซื้อนี้ใช่ไหม?');">
                    <input type="hidden" name="id" value="<?= (int)$order['order_id'] ?>">
                    <button type="submit" class="btn danger same-w">ยกเลิกคำสั่งซื้อ</button>
                </form>
            <?php endif; ?>
        </section>

    </div>

    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/page/js/cart.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop();
        });
    </script>
    <script src="/js/cart-badge.js" defer></script>
</body>

</html>