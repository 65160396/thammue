<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* --- DB --- */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

/* --- ดึงข้อมูลโปรไฟล์ --- */
$stmt = $pdo->prepare("
  SELECT u.email,
         p.first_name, p.last_name, p.phone,
         p.addr_line, p.addr_subdistrict, p.addr_district, p.addr_province, p.addr_postcode
  FROM users u
  LEFT JOIN user_profiles p ON p.user_id = u.id
  WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// --- ดึงสินค้าในตะกร้า ---
$stmt = $pdo->prepare("
  SELECT
    c.product_id,
    c.quantity AS qty,
    p.name, p.price,
    (SELECT pi.image_path
       FROM product_images pi
       WHERE pi.product_id = p.id
       ORDER BY pi.id ASC
       LIMIT 1) AS image
  FROM cart c
  JOIN products p ON p.id = c.product_id
  WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();



/* --- คำนวณยอดรวม --- */
$subtotal = 0;
foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
$shipping = count($items) ? 50 : 0;
$total = $subtotal + $shipping;

function full_addr($p)
{
    if (!$p) return '';
    return trim("{$p['addr_line']} {$p['addr_subdistrict']} อำเภอ{$p['addr_district']} จังหวัด{$p['addr_province']} {$p['addr_postcode']}");
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>ทำการสั่งซื้อ | THAMMUE</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/checkout.css" />
</head>

<body>
    <div class="wrap">
        <h2>ทำการสั่งซื้อ</h2>

        <!-- ที่อยู่จัดส่ง -->
        <div class="section">
            <h3>ที่อยู่ในการจัดส่ง</h3>
            <div class="body">
                <?php if ($profile && ($profile['first_name'] || $profile['addr_line'])): ?>
                    <div class="addr-box">
                        <div class="addr-name">
                            <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
                            (<?= htmlspecialchars($profile['phone'] ?: '-') ?>)
                            <span class="addr-actions">
                                <a href="/page/profile_address.php">แก้ไข</a>
                                <a href="/page/profile_address.php">ตั้งค่า</a>
                            </span>
                        </div>
                        <div class="addr-line"><?= htmlspecialchars(full_addr($profile)) ?></div>
                    </div>
                <?php else: ?>
                    <p class="muted">ยังไม่มีที่อยู่จัดส่งในโปรไฟล์</p>
                    <a class="btn-primary" href="/page/profile_address.php">+ เพิ่มที่อยู่</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- รายการสินค้า -->
        <div class="section">
            <h3>สั่งซื้อสินค้าแล้ว</h3>
            <div class="body">
                <?php if (!count($items)): ?>
                    <div class="empty">ยังไม่มีสินค้าในตะกร้า</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>สินค้า</th>
                                <th class="price">ราคา</th>
                                <th class="price">จำนวน</th>
                                <th class="price">ราคารวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div class="item">
                                            <?php if ($it['image']): ?>
                                                <img src="<?= htmlspecialchars($it['image']) ?>" class="thumb">
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($it['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="price"><?= number_format($it['price'], 0) ?></td>
                                    <td class="price"><?= intval($it['qty']) ?></td>
                                    <td class="price"><?= number_format($it['price'] * $it['qty'], 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- วิธีชำระเงิน -->
        <form class="section" method="post" action="/page/checkout/place_order.php">
            <h3>วิธีการชำระเงิน</h3>
            <div class="body">
                <div class="pay-methods">
                    <label class="pay-chip"><input type="radio" name="pay_method" value="qr" checked> QR พร้อมเพย์</label>
                    <label class="pay-chip"><input type="radio" name="pay_method" value="cod"> เก็บเงินปลายทาง</label>
                    <label class="pay-chip"><input type="radio" name="pay_method" value="kbank"> KBank</label>
                    <label class="pay-chip"><input type="radio" name="pay_method" value="ktb"> Krungthai</label>
                </div>

                <div class="summary">
                    <div class="row"><span>การสั่งซื้อ</span><strong><?= number_format($subtotal, 0) ?></strong></div>
                    <div class="row"><span>การจัดส่ง</span><strong><?= number_format($shipping, 0) ?></strong></div>
                    <div class="row total"><span>ยอดชำระทั้งหมด</span><strong><?= number_format($total, 0) ?></strong></div>
                </div>

                <input type="hidden" name="total" value="<?= $total ?>">
                <button type="submit" class="btn-primary" <?= count($items) === 0 ? 'disabled' : '' ?>>สั่งสินค้า</button>
            </div>
        </form>
    </div>
</body>

</html>