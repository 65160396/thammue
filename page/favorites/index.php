<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/favorites/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

/* DB */
$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* ดึงสินค้าที่ถูกใจ (เฉพาะ type=product) */
$sql = "SELECT p.id, p.name, p.price, p.main_image, s.shop_name, s.province
        FROM favorites f
        JOIN products p ON p.id = f.item_id AND f.item_type = 'product'
        LEFT JOIN shops s ON s.id = p.shop_id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute([$userId]);
$items = $stm->fetchAll();

/* helper */
$WEB_PREFIX = '/page';
function productImg($p)
{
    global $WEB_PREFIX;
    if (!empty($p['main_image'])) {
        return (strpos($p['main_image'], '/uploads/') === 0)
            ? $WEB_PREFIX . $p['main_image']
            : $p['main_image'];
    }
    return $WEB_PREFIX . '/img/placeholder.png';
}
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>รายการโปรด | Thammue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="/css/products.css" />
    <style>
        .fav-header {
            max-width: 1100px;
            margin: 24px auto 8px;
            padding: 0 16px;
        }

        .fav-header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 8px;
        }

        /* เผื่อ products.css ไม่มี display:grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        /* ให้การ์ดเป็นกรอบอ้างอิงตำแหน่ง */
        .product-card {
            position: relative;
        }

        /* ปุ่มหัวใจลอยมุมขวาบน */
        .remove-like {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
            cursor: pointer;
        }

        .remove-like:hover {
            filter: brightness(.96);
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/../partials/site-header.php'; ?>

    <div class="fav-header">
        <h1>รายการโปรด</h1>
    </div>

    <div class="recommended-products" style="padding-top:0">
        <div class="product-grid">
            <?php if (!$items): ?>
                <div class="empty" style="grid-column:1 / -1;">ยังไม่มีรายการโปรด</div>
            <?php else: ?>
                <?php foreach ($items as $it): ?>
                    <div class="product-card">
                        <a class="product-link" href="/page/products/product_detail.php?id=<?= (int)$it['id'] ?>">
                            <img src="<?= productImg($it) ?>" alt="<?= h($it['name']) ?>">
                            <h3><?= h($it['name']) ?></h3>
                            <p><?= is_numeric($it['price']) ? '$' . number_format((float)$it['price'], 0) : h($it['price']) ?></p>
                            <span>จังหวัด<?= h($it['province'] ?: 'ไม่ระบุ') ?></span>
                        </a>
                        <button type="button"
                            class="remove-like"
                            data-id="<?= (int)$it['id'] ?>"
                            title="เอาออกจากรายการโปรด">❤️</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.remove-like');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation(); // กันคลิกลิงก์การ์ดทำงาน

            const id = btn.dataset.id;
            try {
                const res = await fetch('/page/backend/likes_sale/toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        type: 'product',
                        id
                    })
                });
                if (res.status === 401) {
                    location.href = '/page/login.html?next=' + encodeURIComponent(location.pathname);
                    return;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                // ถ้ากลายเป็นไม่ได้ถูกใจแล้ว -> ลบการ์ด
                if (!data.liked) btn.closest('.product-card')?.remove();
            } catch (err) {
                console.error(err);
                alert('เอาออกไม่สำเร็จ');
            }
        });
    </script>
</body>

</html>