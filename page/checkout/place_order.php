<?php
// /page/checkout/place_order.php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /page/login.html?next=' . rawurlencode('/page/checkout/index.php'));
    exit;
}
$userId = (int)$_SESSION['user_id'];

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$payMethod = (($_POST['pay_method'] ?? 'qr') === 'cod') ? 'cod' : 'qr';
$mode      = $_POST['mode'] ?? ''; // '' | 'buy-now'

/** สุ่มรหัสออเดอร์ไม่ซ้ำ */
function makeOrderCode(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $rand = '';
        for ($i = 0; $i < 5; $i++) $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $code = 'ORD' . date('ymd') . $rand;
        $st = $pdo->prepare("SELECT 1 FROM orders WHERE order_code=? LIMIT 1");
        $st->execute([$code]);
    } while ($st->fetchColumn());
    return $code;
}

/** ===== 1) รวบรวม items ที่จะออกออเดอร์ ===== */
$items = [];          // [{product_id, name, price, qty}]
$subtotal = 0;
$shipping = 0;

if ($mode === 'buy-now') {
    // ซื้อทันที: ใช้ข้อมูลจาก session 'buy_now'
    $bn = $_SESSION['buy_now'] ?? null;
    $pid = (int)($bn['product_id'] ?? 0);
    $qty = max(1, (int)($bn['qty'] ?? 1));

    if ($pid <= 0) {
        // ไม่มีข้อมูลซื้อทันที → กลับหน้าหลัก (หรือสินค้า)
        header('Location: /page/main.html');
        exit;
    }

    $q = "SELECT id AS product_id, name, price FROM products WHERE id=? LIMIT 1";
    $s = $pdo->prepare($q);
    $s->execute([$pid]);
    if (!$row = $s->fetch()) {
        header('Location: /page/main.html'); // ไม่พบสินค้า
        exit;
    }
    $row['qty'] = $qty;
    $items[] = $row;
} else {
    // ตะกร้า: ใช้ ids ที่เลือกไว้ตอน checkout
    $selected = $_SESSION['checkout_selected_ids'] ?? [];
    if (!$selected) {
        header('Location: /page/cart/index.php'); // ✅ เส้นทางที่ถูก
        exit;
    }

    $ph = implode(',', array_fill(0, count($selected), '?'));
    $sql = "SELECT c.id AS cart_id, c.product_id, c.quantity AS qty, p.name, p.price
          FROM cart c
          JOIN products p ON p.id = c.product_id
          WHERE c.user_id = ? AND c.id IN ($ph)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $selected));
    $items = $stmt->fetchAll();
    if (!$items) {
        header('Location: /page/cart/index.php'); // ✅
        exit;
    }
}

// คำนวณยอด
foreach ($items as $it) $subtotal += ((float)$it['price'] * (int)$it['qty']);
$shipping = count($items) ? 50 : 0;
$total    = $subtotal + $shipping;

/** ===== 2) บันทึกออเดอร์ ===== */
$pdo->beginTransaction();
try {
    $status    = ($payMethod === 'qr') ? 'pending_payment' : 'cod_pending';
    $orderCode = makeOrderCode($pdo);

    $stmt = $pdo->prepare("
    INSERT INTO orders
      (order_code, user_id, subtotal, shipping_fee, total_amount, pay_method, status, created_at, payment_deadline)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))
  ");
    $stmt->execute([$orderCode, $userId, $subtotal, $shipping, $total, $payMethod, $status]);
    $orderId = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)");
    foreach ($items as $it) {
        $ins->execute([$orderId, (int)$it['product_id'], $it['name'], (float)$it['price'], (int)$it['qty']]);
    }

    // ลบของในตะกร้าเฉพาะกรณีตะกร้า
    if ($mode !== 'buy-now') {
        $selected = $_SESSION['checkout_selected_ids'] ?? [];
        if ($selected) {
            $ph = implode(',', array_fill(0, count($selected), '?'));
            $del = $pdo->prepare("DELETE FROM cart WHERE user_id=? AND id IN ($ph)");
            $del->execute(array_merge([$userId], $selected));
        }
    }

    $pdo->commit();

    // เก็บยอดไว้โชว์ในหน้าชำระเงิน
    $_SESSION['checkout_total'] = $total;

    if ($payMethod === 'qr') {
        header('Location: /page/checkout/payment_qr.php?order_id=' . $orderId . ($mode === 'buy-now' ? '&mode=buy-now' : ''));
    } else {
        header('Location: /page/checkout/order_success.php?order_id=' . $orderId . '&m=cod');
    }
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "สั่งซื้อไม่สำเร็จ: " . $e->getMessage();
}
