<?php
/* expects:
   - $it   : array (id, name, price, main_image, province, category_name, …)
   - $opts : e.g. ['showRemoveLike' => true]
   - helpers: productImg(), h()
*/
$p      = $it;
$img    = productImg($p);
$href   = "/page/products/product_detail.php?id=" . (int)$p['id'];
$name   = h($p['name'] ?? '');
$price  = '$' . number_format((float)($p['price'] ?? 0));
$prov   = !empty($p['province']) ? 'จังหวัด' . h($p['province']) : 'ไม่ระบุจังหวัด';
$cat    = h($p['category_name'] ?? '');
?>
<div class="product-card">
    <?php if (!empty($opts['showRemoveLike'])): ?>
        <button class="like-heart remove-like"
            type="button"
            aria-label="เอาออกจากรายการโปรด"
            data-id="<?= (int)$p['id'] ?>">❤</button>
    <?php endif; ?>

    <!-- ลิงก์คลุมทั้งการ์ด (ย้ายปุ่มออกมาอยู่นอกลิงก์แล้ว) -->
    <a class="card-link" href="<?= h($href) ?>">
        <div class="thumb">
            <img src="<?= h($img) ?>" alt="<?= $name ?>">
        </div>

        <div class="card-body">
            <h3 class="title"><?= $name ?></h3>

            <div class="meta-inline">
                <?php if ($cat !== ''): ?>
                    <span class="label">หมวด:</span><span><?= $cat ?></span>
                    <span class="dot">·</span>
                <?php endif; ?>
                <span><?= $prov ?></span>
            </div>

            <div class="price-strong"><?= $price ?></div>
            <span class="btn-detail">ดูรายละเอียด</span>
        </div>
    </a>
</div>