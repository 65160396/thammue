<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/checkout/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* --- DB --- */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$st = $pdo->prepare("SELECT first_name,last_name,phone,addr_line,addr_subdistrict,addr_district,addr_province,addr_postcode
                     FROM user_profiles WHERE user_id=?");
$st->execute([$userId]);
$pf = $st->fetch();


/* ฟิลด์ที่ต้องครบก่อนสั่งซื้อ */
$needProfile = (
    !$pf ||
    $pf['first_name'] === '' || $pf['last_name'] === '' ||
    !preg_match('/^\d{9,10}$/', (string)$pf['phone']) ||
    $pf['addr_line'] === '' || $pf['addr_subdistrict'] === '' ||
    $pf['addr_district'] === '' || $pf['addr_province'] === '' ||
    $pf['addr_postcode'] === ''
);


if ($needProfile) {
    header('Location: /page/profile.html?need=profile&next=' . rawurlencode('/page/checkout/checkout.php'));
    exit;
}


/* --- helper: สร้าง web path ของรูปสินค้าให้ถูกต้อง --- */
$WEB_PREFIX = '/page';
function productImageWeb(int $productId, ?string $imagePath): string
{
    global $WEB_PREFIX;

    // URL แบบเต็ม
    if ($imagePath && preg_match('~^https?://~i', $imagePath)) {
        return $imagePath;
    }
    // พาธที่ขึ้นต้น /uploads/ → เติม /page ข้างหน้า
    if ($imagePath && strpos($imagePath, '/uploads/') === 0) {
        return $WEB_PREFIX . $imagePath;
    }
    // พาธสัมพัทธ์อื่นๆ (เช่น uploads/... หรือ img/...) → เติม /page/ ข้างหน้า
    if ($imagePath && strpos($imagePath, '/') !== false) {
        $imagePath = ltrim($imagePath, '/');
        return $WEB_PREFIX . '/' . $imagePath;
    }
    // เก็บมาเป็น "ชื่อไฟล์" ล้วนๆ → ประกอบพาธโฟลเดอร์ของ product นั้น
    if ($imagePath) {
        return $WEB_PREFIX . "/uploads/products/{$productId}/" . $imagePath;
    }

    // Fallback: หา main_* ในโฟลเดอร์อัปโหลด
    $dirFs = realpath(__DIR__ . "/../uploads/products/" . $productId);
    if ($dirFs && is_dir($dirFs)) {
        $found = glob($dirFs . "/main_*.*");
        if ($found) {
            return $WEB_PREFIX . "/uploads/products/{$productId}/" . basename($found[0]);
        }
        $any = glob($dirFs . "/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}", GLOB_BRACE);
        if ($any) {
            return $WEB_PREFIX . "/uploads/products/{$productId}/" . basename($any[0]);
        }
    }
    return $WEB_PREFIX . "/img/placeholder.png";
}

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

/* ====== สลับโหมด: buy-now หรือ cart ====== */
$mode = $_GET['mode'] ?? '';  // ถ้ามี ?mode=buy-now จะใช้สินค้าจาก session buy_now
$items = [];
$subtotal = 0;
$shipping = 0;

/* ---------- โหมด BUY NOW: ไม่ยุ่งกับตะกร้า ---------- */
if ($mode === 'buy-now' && !empty($_SESSION['buy_now'])) {
    $bn  = $_SESSION['buy_now'];
    $pid = (int)($bn['product_id'] ?? 0);
    $qty = max(1, (int)($bn['qty'] ?? 1));

    if ($pid > 0) {
        $q = "
          SELECT
            p.id AS product_id, p.name, p.price,
            (SELECT pi.image_path
               FROM product_images pi
               WHERE pi.product_id = p.id
               ORDER BY pi.id ASC
               LIMIT 1) AS image
          FROM products p
          WHERE p.id = ?
          LIMIT 1
        ";
        $s = $pdo->prepare($q);
        $s->execute([$pid]);
        if ($row = $s->fetch()) {
            $row['qty'] = $qty;
            $items[]    = $row;
        }
    }

    foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
    $shipping = count($items) ? 50 : 0;

    /* ---------- โหมด CART: ของเดิม ดึงเฉพาะที่เลือก ---------- */
} else {
    // รับรายการที่เลือกจากหน้า cart
    $selected = isset($_POST['selected']) && is_array($_POST['selected'])
        ? array_values(array_unique(array_map('intval', $_POST['selected'])))
        : [];

    // เก็บไว้ใน session เผื่อ refresh/กลับมา
    if ($selected) {
        $_SESSION['checkout_selected_ids'] = $selected;
    } elseif (isset($_SESSION['checkout_selected_ids'])) {
        $selected = $_SESSION['checkout_selected_ids'];
    }

    // ดึงสินค้าในตะกร้า (เฉพาะที่เลือก)
    $whereIn = " AND 1=0 ";
    $params  = [$userId];
    if (!empty($selected)) {
        $ph      = implode(',', array_fill(0, count($selected), '?'));
        $whereIn = " AND c.id IN ($ph) ";
        $params  = array_merge($params, $selected);
    }

    $sql = "
      SELECT
        c.id AS cart_id,
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
      WHERE c.user_id = ? $whereIn
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
    $shipping = count($items) ? 50 : 0;
}

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

<body class="checkout-page">

    <?php
    $HEADER_NO_CATS = true;
    include __DIR__ . '/../partials/site-header.php';
    ?>

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
                                <a href="/page/profile.html?next=/page/checkout/index.php">แก้ไข</a>
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
            <h3><?= $mode === 'buy-now' ? 'สินค้า (ซื้อทันที)' : 'สั่งซื้อสินค้าแล้ว' ?></h3>
            <div class="body">
                <?php if (!count($items)): ?>
                    <div class="empty">
                        <?= $mode === 'buy-now'
                            ? 'ไม่พบรายการซื้อทันที กรุณากลับไปหน้าสินค้า'
                            : 'ยังไม่มีสินค้าในตะกร้า'
                        ?>
                    </div>
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
                            <?php foreach ($items as $it):
                                $imgWeb = productImageWeb((int)$it['product_id'], $it['image'] ?? null);
                            ?>
                                <tr>
                                    <td>
                                        <div class="item">
                                            <img src="<?= htmlspecialchars($imgWeb) ?>" class="thumb" alt="<?= htmlspecialchars($it['name']) ?>">
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
                </div>

                <div class="summary">
                    <div class="row"><span>การสั่งซื้อ</span><strong><?= number_format($subtotal, 0) ?></strong></div>
                    <div class="row"><span>การจัดส่ง</span><strong><?= number_format($shipping, 0) ?></strong></div>
                    <div class="row total"><span>ยอดชำระทั้งหมด</span><strong><?= number_format($subtotal + $shipping, 0) ?></strong></div>
                </div>

                <input type="hidden" name="total" value="<?= $total ?>">
                <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                <button type="submit" class="btn-primary" <?= count($items) === 0 ? 'disabled' : '' ?>>สั่งสินค้า</button>
            </div>
        </form>
    </div>

    <script src="/js/me.js"></script>
    <script src="/js/user-menu.js"></script>
    <script src="/js/store/shop-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            toggleOpenOrMyShop?.();
        });
    </script>
    <script src="/js/cart-badge.js" defer></script>
    <script src="/js/header-noti.js"></script>
    <script src="/js/notify-poll.js"></script>
</body>

</html>