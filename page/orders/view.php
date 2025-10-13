<?php
// /page/orders/view.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

// ---- DB ----
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

/* ==================== helpers ==================== */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($n)
{
    return number_format((float)$n, 2);
}

/** คืน path ของรูปแรกใน /uploads/products/{product_id}/ (รองรับ jpg/jpeg/png/webp/gif) */
function product_image_url(int $pid): string
{
    // view.php อยู่ที่ /page/orders/ => ขึ้นไปสองระดับถึง root
    $dir = __DIR__ . '/../../uploads/products/' . $pid;
    if (is_dir($dir)) {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $files = glob($dir . '/*.' . $ext);
            if (!empty($files)) {
                return '/uploads/products/' . $pid . '/' . basename($files[0]);
            }
        }
    }
    return '/img/noimg.png';
}

/** คืนรายชื่อคอลัมน์ของตาราง (ใช้เช็ค schema) */
function table_columns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
        $stmt->execute();
        return array_map(fn($r) => $r['Field'], $stmt->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/** เลือกชื่อคอลัมน์แรกที่มีจริงจาก candidate list */
function pick_col(array $cols, array $candidates): ?string
{
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}
/* ================================================= */

// ---- รับค่า order_id ----
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order id');
}

// ---- ดึงข้อมูลออเดอร์หลัก ----
$stmt = $pdo->prepare("
  SELECT o.id AS order_id, o.user_id, o.created_at, o.status, o.total_amount
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

// ===== เติมที่อยู่/วิธีชำระ/สถานะเวลา แบบไดนามิกตาม schema =====
$ordCols = table_columns($pdo, 'orders');
$map = [
    'ship_name'        => pick_col($ordCols, ['receiver_name', 'fullname', 'full_name', 'name']),
    'ship_phone'       => pick_col($ordCols, ['receiver_phone', 'phone', 'tel', 'mobile']),
    'ship_addr_line'   => pick_col($ordCols, ['address', 'address_line', 'address1', 'addr']),
    'ship_subdistrict' => pick_col($ordCols, ['subdistrict', 'sub_district', 'tambon']),
    'ship_district'    => pick_col($ordCols, ['district', 'amphoe']),
    'ship_province'    => pick_col($ordCols, ['province', 'prov']),
    'ship_postcode'    => pick_col($ordCols, ['postcode', 'zip', 'zipcode']),
    'payment_method'   => pick_col($ordCols, ['payment_method', 'pay_method']),
    'paid_at'          => pick_col($ordCols, ['paid_at', 'payment_at']),
    'shipped_at'       => pick_col($ordCols, ['shipped_at', 'shipping_at']),
    'completed_at'     => pick_col($ordCols, ['completed_at', 'complete_at']),
    'tracking_company' => pick_col($ordCols, ['tracking_company', 'courier']),
    'tracking_no'      => pick_col($ordCols, ['tracking_no', 'tracking', 'consignment_no']),
    'payment_slip_path' => pick_col($ordCols, ['payment_slip_path', 'slip_path', 'slip'])
];

// สร้าง SELECT เพิ่มจาก orders (ใส่ NULL ให้ alias ที่ไม่มีคอลัมน์)
$selectParts = [];
foreach (
    [
        'ship_name',
        'ship_phone',
        'ship_addr_line',
        'ship_subdistrict',
        'ship_district',
        'ship_province',
        'ship_postcode',
        'payment_method',
        'paid_at',
        'shipped_at',
        'completed_at',
        'tracking_company',
        'tracking_no',
        'payment_slip_path'
    ] as $alias
) {
    $selectParts[] = $map[$alias] ? "o.`{$map[$alias]}` AS `$alias`" : "NULL AS `$alias`";
}
$sql = "SELECT " . implode(", ", $selectParts) . " FROM orders o WHERE o.id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$orderId]);
$extra = $stmt->fetch() ?: [];
foreach ($extra as $k => $v) $order[$k] = $v ?? '';

// ถ้า ship_* ยังว่าง ลอง fallback ไป users
if (empty($order['ship_name']) && empty($order['ship_addr_line'])) {
    $userCols = table_columns($pdo, 'users');
    $uMap = [
        'ship_name'        => pick_col($userCols, ['fullname', 'full_name', 'name', 'username']),
        'ship_phone'       => pick_col($userCols, ['phone', 'tel', 'mobile']),
        'ship_addr_line'   => pick_col($userCols, ['address', 'address_line', 'address1', 'addr']),
        'ship_subdistrict' => pick_col($userCols, ['subdistrict', 'sub_district', 'tambon']),
        'ship_district'    => pick_col($userCols, ['district', 'amphoe']),
        'ship_province'    => pick_col($userCols, ['province', 'prov']),
        'ship_postcode'    => pick_col($userCols, ['postcode', 'zip', 'zipcode']),
    ];
    $parts = [];
    foreach (['ship_name', 'ship_phone', 'ship_addr_line', 'ship_subdistrict', 'ship_district', 'ship_province', 'ship_postcode'] as $alias) {
        $parts[] = $uMap[$alias] ? "u.`{$uMap[$alias]}` AS `$alias`" : "NULL AS `$alias`";
    }
    if ($parts) {
        $sql = "SELECT " . implode(", ", $parts) . " FROM users u WHERE u.id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $uAddr = $stmt->fetch() ?: [];
        foreach ($uAddr as $k => $v) if (empty($order[$k])) $order[$k] = $v ?? '';
    }
}

// ---- ดึงรายการสินค้า แล้วเติมรูปจากโฟลเดอร์ ----
$it = $pdo->prepare("
  SELECT
    i.product_id, i.qty, i.price,
    p.name AS product_name
  FROM order_items i
  INNER JOIN products p ON p.id = i.product_id
  WHERE i.order_id = ?
  ORDER BY i.id ASC
");
$it->execute([$orderId]);
$items = $it->fetchAll();

// เติม image_url ให้แต่ละแถว
foreach ($items as &$row) {
    $row['image_url'] = product_image_url((int)$row['product_id']);
}
unset($row);
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
    <?php
    $HEADER_NO_CATS = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>
    <div class="wrap">

        <!-- สรุปคำสั่งซื้อ -->
        <section class="card order-summary">
            <div class="left">
                <div class="oid">Order #<?= h($order['order_id']) ?></div>
                <div class="meta">
                    <span class="label">สั่งซื้อเมื่อ:</span> <?= h($order['created_at']) ?>
                    <?php if (!empty($order['paid_at'])): ?> • <span class="label">ชำระเมื่อ:</span> <?= h($order['paid_at']) ?><?php endif; ?>
                        <?php if (!empty($order['shipped_at'])): ?> • <span class="label">ส่งเมื่อ:</span> <?= h($order['shipped_at']) ?><?php endif; ?>
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
                            <img class="thumb" src="<?= h($row['image_url']) ?>" alt="">
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
            <?php if (!empty($order['tracking_no'])): ?>
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
            <?php elseif ($order['status'] === 'pending_payment' || $order['status'] === 'cod_pending'): ?>
                <p class="muted">ยังไม่มีสลิป</p>
            <?php else: ?>
                <p class="muted">ไม่พบสลิป</p>
            <?php endif; ?>
        </section>

        <!-- ปุ่มการทำงาน -->
        <section class="actions">
            <a class="btn ghost" href="/page/orders">← กลับไปหน้าคำสั่งซื้อ</a>
            <?php if ($order['status'] === 'pending_payment' || $order['status'] === 'cod_pending'): ?>
                <a class="btn primary" href="/page/checkout/payment_qr.php?order_id=<?= (int)$order['order_id'] ?>">ชำระเงินด้วย QR</a>
            <?php elseif ($order['status'] === 'shipped'): ?>
                <!-- (อนาคต) ปุ่มยืนยันรับสินค้า -->
                <!-- <form method="post" action="/page/orders/confirm.php" class="inline">
           <input type="hidden" name="id" value="<?= (int)$order['order_id'] ?>">
           <button class="btn">ยืนยันรับสินค้า</button>
         </form> -->
            <?php endif; ?>
        </section>
    </div>
</body>

</html>