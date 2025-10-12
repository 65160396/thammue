<?php
// /page/orders/view.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

// ---- DB (แก้คอนฟิกตามของโปรเจกต์) ----
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---- รับค่า order_id ----
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order id');
}

// ---- ดึงข้อมูลออเดอร์ (ตรวจสิทธิ์เจ้าของออเดอร์ด้วย) ----
// หมายเหตุ: ฟิลด์ด้านล่างเป็น “โครงมาตรฐานที่แนะนำ”
// ถ้าชื่อตาราง/คอลัมน์ของคุณต่างกัน ให้ map ให้ตรง
$stmt = $pdo->prepare("
  SELECT
    o.id AS order_id, o.user_id, o.created_at, o.paid_at, o.shipped_at, o.completed_at,
    o.status, o.payment_method, o.total_amount,
    o.ship_name, o.ship_phone, o.ship_addr_line, o.ship_subdistrict, o.ship_district, o.ship_province, o.ship_postcode,
    o.tracking_company, o.tracking_no,
    o.payment_slip_path
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

// ---- ดึงรายการสินค้าในออเดอร์ ----
// จำเป็นต้องมีตาราง order_items(product_id, qty, price) และ products(id, name, image_url)
$it = $pdo->prepare("
  SELECT
    i.product_id, i.qty, i.price,
    p.name AS product_name, p.image_url
  FROM order_items i
  LEFT JOIN products p ON p.id = i.product_id
  WHERE i.order_id = ?
  ORDER BY i.id ASC
");
$it->execute([$orderId]);
$items = $it->fetchAll();

// ---- helper ----
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($n)
{
    return number_format((float)$n, 2);
}
?>
<!DOCTYPE html>
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
    <ul class="top-nav">
        <li><a href="/page/main.html">ซื้อสินค้า</a></li>
        <li><span class="top-divider">|</span></li>
        <li><a href="/page/orders">คำสั่งซื้อของฉัน</a></li>
        <li><span class="top-divider">|</span></li>
        <li aria-current="page"><strong>รายละเอียด</strong></li>
    </ul>

    <div class="wrap">
        <a class="btn ghost back" href="/page/orders">← กลับไปหน้าคำสั่งซื้อ</a>

        <!-- สรุปคำสั่งซื้อ -->
        <section class="card order-summary">
            <div class="left">
                <div class="oid">Order #<?= h($order['order_id']) ?></div>
                <div class="meta">
                    <span class="label">สั่งซื้อเมื่อ:</span> <?= h($order['created_at']) ?>
                    <?php if ($order['paid_at']): ?> • <span class="label">ชำระเมื่อ:</span> <?= h($order['paid_at']) ?><?php endif; ?>
                        <?php if ($order['shipped_at']): ?> • <span class="label">ส่งเมื่อ:</span> <?= h($order['shipped_at']) ?><?php endif; ?>
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
                            <img class="thumb" src="<?= h($row['image_url'] ?: '/img/noimg.png') ?>" alt="">
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

        <!-- ที่อยู่จัดส่ง -->
        <section class="card shipping">
            <h2>ที่อยู่จัดส่ง</h2>
            <div class="addr">
                <div><strong><?= h($order['ship_name'] ?: '—') ?></strong> • <?= h($order['ship_phone'] ?: '—') ?></div>
                <div><?= h($order['ship_addr_line'] ?: '—') ?></div>
                <div><?= h($order['ship_subdistrict'] ?: '') ?> <?= h($order['ship_district'] ?: '') ?> <?= h($order['ship_province'] ?: '') ?> <?= h($order['ship_postcode'] ?: '') ?></div>
            </div>
        </section>

        <!-- จัดส่ง/เลขพัสดุ -->
        <section class="card tracking">
            <h2>การจัดส่ง</h2>
            <?php if ($order['tracking_no']): ?>
                <div>บริษัทขนส่ง: <strong><?= h($order['tracking_company'] ?: '—') ?></strong></div>
                <div>หมายเลขพัสดุ: <strong><?= h($order['tracking_no']) ?></strong></div>
            <?php else: ?>
                <p class="muted">ยังไม่จัดส่ง</p>
            <?php endif; ?>
        </section>

        <!-- หลักฐานการชำระเงิน -->
        <section class="card payment-proof">
            <h2>หลักฐานการชำระเงิน</h2>
            <?php if (!empty($order['payment_slip_path'])): ?>
                <a href="<?= h($order['payment_slip_path']) ?>" target="_blank">
                    <img class="slip" src="<?= h($order['payment_slip_path']) ?>" alt="สลิปชำระเงิน">
                </a>
            <?php elseif ($order['status'] === 'pending'): ?>
                <p class="muted">ยังไม่มีสลิป</p>
                <a class="btn primary" href="/page/checkout/payment_qr.php?order_id=<?= (int)$order['order_id'] ?>">ชำระเงินด้วย QR</a>
                <!-- ตัวเลือกอัปโหลดสลิป (ถ้าทำ) -->
                <!-- <a class="btn" href="/page/orders/upload_slip.php?id=<?= (int)$order['order_id'] ?>">อัปโหลดสลิป</a> -->
            <?php else: ?>
                <p class="muted">ไม่พบสลิป</p>
            <?php endif; ?>
        </section>

        <!-- ปุ่มการทำงาน -->
        <section class="actions">
            <a class="btn ghost" href="/page/orders">← กลับไปหน้าคำสั่งซื้อ</a>
            <?php if ($order['status'] === 'pending'): ?>
                <a class="btn primary" href="/page/checkout/payment_qr.php?order_id=<?= (int)$order['order_id'] ?>">ไปชำระเงิน</a>
            <?php elseif ($order['status'] === 'shipped'): ?>
                <!-- ที่ระบบจริงค่อยทำ endpoint ยืนยันรับสินค้า -->
                <!-- <form method="post" action="/page/orders/confirm.php" class="inline">
          <input type="hidden" name="id" value="<?= (int)$order['order_id'] ?>">
          <button class="btn">ยืนยันรับสินค้า</button>
        </form> -->
            <?php endif; ?>
        </section>
    </div>
</body>

</html>