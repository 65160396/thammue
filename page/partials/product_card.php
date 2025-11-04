<?php
/* ✅ ตัวไฟล์นี้เป็น “component” สำหรับแสดงการ์ดสินค้า 1 ชิ้น (Product Card)
   ใช้ในหน้ารวมสินค้า, หน้ารายการโปรด, หรือหน้าร้านค้า
   โดยรับตัวแปร:
   - $it   : ข้อมูลสินค้าชิ้นนั้น (id, name, price, main_image, province, category_name ฯลฯ)
   - $opts : ตัวเลือกเสริม เช่น ['showRemoveLike' => true] เพื่อโชว์ปุ่มลบออกจากรายการโปรด
   - helpers: productImg(), h()  → ฟังก์ชันช่วยแปลง path รูป และ escape HTML
*/

$p      = $it;
$img    = productImg($p); // ✅ ดึง URL รูปสินค้า (มี fallback ถ้าไม่มีรูป)
$href   = "/page/products/product_detail.php?id=" . (int)$p['id']; // ✅ ลิงก์ไปหน้ารายละเอียดสินค้า
$name   = h($p['name'] ?? '');
$price  = '$' . number_format((float)($p['price'] ?? 0)); // ✅ แสดงราคาเป็นตัวเลขพร้อม $
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

    <!-- ✅ การ์ดสินค้าทั้งใบเป็นลิงก์ ไปยังหน้ารายละเอียดสินค้า -->
    <a class="card-link" href="<?= h($href) ?>">
        <div class="thumb">
            <img src="<?= h($img) ?>" alt="<?= $name ?>"><!-- ✅ แสดงรูปสินค้า -->
        </div>

        <div class="card-body">
            <h3 class="title"><?= $name ?></h3><!-- ✅ ชื่อสินค้า -->
 <!-- ✅ แสดงหมวดหมู่ + จังหวัด -->
            <div class="meta-inline">
                <?php if ($cat !== ''): ?>
                    <span class="label">หมวด:</span><span><?= $cat ?></span>
                    <span class="dot">·</span>
                <?php endif; ?>
                <span><?= $prov ?></span>
            </div>
 <!-- ✅ แสดงราคา -->
            <div class="price-strong"><?= $price ?></div>
             <!-- ✅ ปุ่มดูรายละเอียด -->
            <span class="btn-detail">ดูรายละเอียด</span>
        </div>
    </a>
</div>