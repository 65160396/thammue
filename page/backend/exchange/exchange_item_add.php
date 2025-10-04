<?php require __DIR__ . '/../../db.php'; ?>
<!doctype html>
<meta charset="utf-8">
<style>
    .step {
        display: none
    }

    .step.active {
        display: block
    }
</style>

<form id="exForm" action="/page/backend/exchange/exchange_item_store.php" method="post" enctype="multipart/form-data">

    <!-- STEP 1 -->
    <section class="step active" data-step="1">
        <h3>ข้อมูลสินค้า</h3>
        <input name="title" placeholder="ชื่อสินค้า" required>

        <select name="category_id" required>
            <option value="">— เลือกหมวดหมู่ —</option>
            <?php foreach ($pdo->query("SELECT id,name FROM exchange_categories ORDER BY name") as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <textarea name="description" placeholder="รายละเอียดสินค้า"></textarea>

        <label>รูปสินค้า</label>
        <input type="file" name="images[]" accept="image/*" multiple required>

        <button type="button" onclick="goto(2)">ถัดไป</button>
    </section>

    <!-- STEP 2 -->
    <section class="step" data-step="2">
        <h3>สินค้าที่ต้องการ</h3>
        <input name="want_title" placeholder="ชื่อสินค้าที่ต้องการ (ถ้าไม่มีข้ามได้)">
        <select name="want_category_id">
            <option value="">— ไม่ระบุ —</option>
            <?php foreach ($pdo->query("SELECT id,name FROM exchange_categories ORDER BY name") as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <textarea name="want_note" placeholder="สี/สไตล์/เงื่อนไขการแลก"></textarea>

        <button type="button" onclick="goto(1)">ย้อนกลับ</button>
        <button type="button" onclick="goto(3)">ถัดไป</button>
    </section>

    <!-- STEP 3 -->
    <section class="step" data-step="3">
        <h3>สถานที่นัดรับ</h3>
        <select name="province" required>
            <option value="">— เลือกจังหวัด —</option>
            <!-- เติมตัวเลือกจังหวัดของคุณเอง หรือปล่อยเป็น input text ก็ได้ -->
        </select>

        <select name="district" required>
            <option value="">— เลือกอำเภอ/เขต —</option>
        </select>

        <select name="subdistrict">
            <option value="">— เลือกตำบล/แขวง —</option>
        </select>

        <input name="zipcode" placeholder="รหัสไปรษณีย์">
        <input name="place_detail" placeholder="บ้านเลขที่/หมู่/ซอย/ถนน">

        <button type="button" onclick="goto(2)">ย้อนกลับ</button>
        <button type="submit">ยืนยันการอัปโหลดสินค้า</button>
    </section>
</form>

<script>
    function goto(n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.querySelector('.step[data-step="' + n + '"]').classList.add('active');
    }
</script>