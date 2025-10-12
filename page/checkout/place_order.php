<?php
// /page/checkout/place_order.php  ← ให้ path ตรงกับ action ใน checkout.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location:/page/login.html');
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$payMethod = ($_POST['pay_method'] ?? 'qr') === 'cod' ? 'cod' : 'qr';

// ── ใช้รายการที่เลือกจาก checkout (กันเผลอสั่งทั้งตะกร้า)
$selected = $_SESSION['checkout_selected_ids'] ?? [];
if (!$selected) {
    // ถ้ายังไม่มี selected ให้กันเคสผิดพลาดกลับไป cart
    header('Location:/page/cart.php');
    exit;
}

// ดึงรายการจริงจาก cart ของ user ตาม id ที่เลือก
$ph = implode(',', array_fill(0, count($selected), '?'));
$sql = "
  SELECT c.id AS cart_id, c.product_id, c.quantity AS qty,
         p.name, p.price
  FROM cart c
  JOIN products p ON p.id = c.product_id
  WHERE c.user_id = ? AND c.id IN ($ph)
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$userId], $selected));
$items = $stmt->fetchAll();
if (!$items) {
    header('Location:/page/cart.php');
    exit;
}

// ── คำนวณยอด (ฝั่งเซิร์ฟเวอร์เสมอ)
$subtotal = 0;
foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
$shipping = count($items) ? 50 : 0;
$total    = $subtotal + $shipping;

// ── สร้างออเดอร์ + ไอเท็ม + ลบออกจาก cart (ภายใต้ transaction)
$pdo->beginTransaction();
try {
    $status = $payMethod === 'qr' ? 'pending_payment' : 'cod_pending';

    // orders
    $stmt = $pdo->prepare("
    INSERT INTO orders
      (user_id, subtotal, shipping_fee, total_amount, pay_method, status, created_at)
    VALUES
      (?,       ?,        ?,            ?,            ?,          ?,      NOW())
  ");
    $stmt->execute([$userId, $subtotal, $shipping, $total, $payMethod, $status]);
    $orderId = (int)$pdo->lastInsertId();

    // order_items
    $insIt = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, name, price, qty)
    VALUES (?, ?, ?, ?, ?)
  ");
    foreach ($items as $it) {
        $insIt->execute([
            $orderId,
            (int)$it['product_id'],
            $it['name'],
            (float)$it['price'],
            (int)$it['qty']
        ]);
    }

    // ลบเฉพาะแถวที่เลือกออกจาก cart
    $del = $pdo->prepare("DELETE FROM cart WHERE user_id=? AND id IN ($ph)");
    $del->execute(array_merge([$userId], $selected));

    $pdo->commit();

    // ── ไปต่อ: QR → หน้า QR; COD → Success
    if ($payMethod === 'qr') {
        // เก็บ total ให้หน้า payment_qr.php ใช้สร้าง QR
        $_SESSION['checkout_total'] = $total;
        header("Location: /page/checkout/payment_qr.php?order_id=" . $orderId);
    } else {
        header("Location: /page/checkout/order_success.php?order_id=" . $orderId . "&m=cod");
    }
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "สั่งซื้อไม่สำเร็จ: " . $e->getMessage();
}
