<?php
// /page/cart/index.php
ini_set('display_errors', 1);
error_reporting(E_ALL); // ช่วย debug ตอนพัฒนา

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/cart/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ดึงรายการตะกร้า + ข้อมูลสินค้า
$sql = "SELECT
          c.product_id, c.quantity,
          p.name, p.price, p.main_image
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute([$userId]);
$items = $stm->fetchAll();

// helper
$WEB_PREFIX = '/page';
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function imgPath($row)
{
    global $WEB_PREFIX;
    if (!empty($row['main_image'])) {
        return (strpos($row['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $row['main_image']
            : $row['main_image'];
    }
    return $WEB_PREFIX . '/img/placeholder.png';
}

// รวมยอด
$total = 0;
foreach ($items as $r) {
    $price = is_numeric($r['price']) ? (float)$r['price'] : 0;
    $qty   = max(1, (int)$r['quantity']);
    $total += $price * $qty;
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <title>ตะกร้าสินค้า | Thammue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/css/style.css" />
    <style>
        .cart-wrap {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .cart-list {
            display: grid;
            gap: 12px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 72px 1fr auto;
            align-items: center;
            gap: 12px;
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .cart-item img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-title {
            font-weight: 700;
        }

        .cart-price {
            font-weight: 700;
        }

        .cart-summary {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 16px;
            align-items: center;
        }

        .btn-primary {
            background: #111;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../partials/site-header.php'; ?>

    <div class="cart-wrap">
        <h1>ตะกร้าสินค้า</h1>

        <?php if (!$items): ?>
            <p>ตะกร้าว่างเปล่า</p>
        <?php else: ?>
            <div class="cart-list">
                <?php foreach ($items as $r): ?>
                    <div class="cart-item">
                        <img src="<?= imgPath($r) ?>" alt="<?= h($r['name']) ?>">
                        <div>
                            <div class="cart-title"><?= h($r['name']) ?></div>
                            <div>จำนวน: <?= (int)$r['quantity'] ?></div>
                        </div>
                        <div class="cart-price">
                            $<?= number_format((float)$r['price'] * (int)$r['quantity'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div><strong>รวมทั้งหมด:</strong> $<?= number_format($total, 2) ?></div>
                <button class="btn-primary">ชำระเงิน</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ตั้ง badge ให้ตรงกับจำนวนจริงเมื่อเข้าหน้า cart
        window.dispatchEvent(new CustomEvent('cart:set', {
            detail: {
                count: <?= count($items) ?>
            }
        }));
    </script>

</body>

</html>