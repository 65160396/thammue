<?php
// /page/checkout/place_order.php
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

/* ---- ฟังก์ชันสุ่มรหัสออเดอร์ (A–Z, 0–9 ไม่มีขีด) และรับประกันไม่ซ้ำ ---- */
function makeOrderCode(PDO $pdo): string
{
    // รูปแบบ: ORD + yymmdd + 5 ตัว (A-Z, 2-9) – ไม่มีขีด
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ตัด 0 O I 1 เพื่อลดสับสน
    do {
        $rand = '';
        for ($i = 0; $i < 5; $i++) {
            $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = 'ORD' . date('ymd') . $rand;

        $st = $pdo->prepare("SELECT 1 FROM orders WHERE order_code=? LIMIT 1");
        $st->execute([$code]);
        $exists = (bool)$st->fetchColumn();
    } while ($exists);

    return $code;
}



/* ---- ใช้รายการที่เลือกจาก checkout ---- */
$selected = $_SESSION['checkout_selected_ids'] ?? [];
if (!$selected) {
    header('Location:/page/cart.php');
    exit;
}

$ph = implode(',', array_fill(0, count($selected), '?'));
$sql = "SELECT c.id AS cart_id, c.product_id, c.quantity AS qty, p.name, p.price
        FROM cart c JOIN products p ON p.id=c.product_id
        WHERE c.user_id=? AND c.id IN ($ph)";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$userId], $selected));
$items = $stmt->fetchAll();
if (!$items) {
    header('Location:/page/cart.php');
    exit;
}

$subtotal = 0;
foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
$shipping = count($items) ? 50 : 0;
$total    = $subtotal + $shipping;

$pdo->beginTransaction();
try {
    $status = $payMethod === 'qr' ? 'pending_payment' : 'cod_pending';
    $orderCode = makeOrderCode($pdo); // << รหัสสุ่ม

    // INSERT orders พร้อม order_code + deadline 24 ชม.
    $stmt = $pdo->prepare("
      INSERT INTO orders
        (order_code, user_id, subtotal, shipping_fee, total_amount, pay_method, status, created_at, payment_deadline)
      VALUES
        (?,          ?,       ?,        ?,            ?,            ?,          ?,      NOW(),   DATE_ADD(NOW(), INTERVAL 1 DAY))
    ");
    $stmt->execute([$orderCode, $userId, $subtotal, $shipping, $total, $payMethod, $status]);
    $orderId = (int)$pdo->lastInsertId();

    // รายการสินค้า
    $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)");
    foreach ($items as $it) {
        $ins->execute([$orderId, (int)$it['product_id'], $it['name'], (float)$it['price'], (int)$it['qty']]);
    }

    // ลบตะกร้าที่เลือก
    $del = $pdo->prepare("DELETE FROM cart WHERE user_id=? AND id IN ($ph)");
    $del->execute(array_merge([$userId], $selected));

    $pdo->commit();

    if ($payMethod === 'qr') {
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
