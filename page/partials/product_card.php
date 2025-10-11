<?php
// ต้องมี $it ; ใช้ helper h(), productImg()
$inCart = !empty($it['in_cart']);
?>
<div class="product-card">
    <a class="product-link" href="/page/products/product_detail.php?id=<?= (int)$it['id'] ?>">
        <img src="<?= productImg($it) ?>" alt="<?= h($it['name']) ?>">
        <h3><?= h($it['name']) ?></h3>
        <p><?= is_numeric($it['price']) ? '$' . number_format((float)$it['price'], 0) : h($it['price']) ?></p>
        <span>จังหวัด<?= h($it['province'] ?: 'ไม่ระบุ') ?></span>
    </a>

    <!-- ปุ่มตะกร้า -->
    <button type="button"
        class="add-cart-bottom <?= $inCart ? 'is-in-cart' : '' ?>"
        data-id="<?= (int)$it['id'] ?>">
        <?= $inCart ? 'อยู่ในตะกร้า' : 'เพิ่มใส่ตะกร้า' ?>
    </button>

    <?php if (!empty($opts['showRemoveLike'])): ?>
        <button type="button"
            class="remove-like"
            data-id="<?= (int)$it['id'] ?>"
            title="เอาออกจากรายการโปรด">❤️</button>
    <?php endif; ?>
</div>