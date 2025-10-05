<?php require __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>อัปโหลดสินค้า | THAMMUE</title>
    <link rel="stylesheet" href="/css/instyle.css" />
    <link rel="stylesheet" href="/css/exuplode.css" />
    <style>
        /* สไตล์เล็กน้อยให้หน้าฟอร์มเหมือนภาพ */
        body {
            font-family: "Noto Sans Thai", system-ui, -apple-system, Segoe UI, Arial, sans-serif;
        }

        .container {
            width: min(900px, 92%);
            margin: 40px auto;
        }

        .field {
            margin: 16px 0;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }

        textarea {
            min-height: 110px;
        }

        .hint {
            font-size: 12px;
            color: #a33;
            margin-left: 8px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 12px;
            border: 0;
            background: #111;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .muted {
            color: #777;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>ข้อมูลสินค้า</h2>
        <form action="/actions/product_save.php" method="post" enctype="multipart/form-data">
            <div class="field">
                <label>*ชื่อสินค้า</label>
                <input type="text" name="name" required />
            </div>

            <div class="field">
                <label>*หมวดหมู่สินค้า</label>
                <select name="category_id" required>
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php
                    $res = $mysqli->query("SELECT id, name FROM categories ORDER BY name");
                    while ($cat = $res->fetch_assoc()):
                    ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="field">
                <label>*รายละเอียดสินค้า</label>
                <textarea name="description" required></textarea>
            </div>

            <!-- เผื่อการ์ดแนะนำ -->
            <div class="row">
                <div class="field">
                    <label>ราคา (ใส่ได้/ไม่ใส่ก็ได้)</label>
                    <input type="number" step="0.01" name="price" placeholder="เช่น 99.00" />
                </div>
                <div class="field">
                    <label>จังหวัด (ใส่ได้/ไม่ใส่ก็ได้)</label>
                    <input type="text" name="province" placeholder="เช่น ชลบุรี" />
                </div>
            </div>

            <div class="field">
                <label>*รูปปก (main)</label>
                <input type="file" name="main_image" accept="image/*" required />
            </div>

            <div class="field">
                <label>รูปสินค้าเพิ่มเติม <span class="hint">กรณีมีรูปมากกว่า 1</span></label>
                <!-- ให้เลือกได้หลายไฟล์ครั้งเดียว -->
                <input type="file" name="images[]" accept="image/*" multiple />
                <div class="muted">อัปได้หลายรูปพร้อมกัน (จะเก็บลงตาราง product_images)</div>
            </div>

            <div class="field">
                <button class="btn" type="submit">ถัดไป</button>
            </div>
        </form>
    </div>
</body>

</html>