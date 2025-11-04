<?php
// /page/cart/update_qty.php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$payload   = json_decode(file_get_contents('php://input'), true);
$productId = (int)($payload['id']  ?? 0);
$qty       = (int)($payload['qty'] ?? 0);
if ($productId <= 0 || $qty < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad input']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if ($qty === 0) {
    // ลบรายการเมื่อเป็น 0
    $pdo->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?")->execute([$userId, $productId]);
} else {
    // อัปเดต/แทรก
    $pdo->prepare("
    INSERT INTO cart (user_id, product_id, quantity)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
  ")->execute([$userId, $productId, $qty]);
}

// ดึงราคาแถวนี้ (ถ้าถูกลบ qty=0 ก็จะเป็น 0)
$stm = $pdo->prepare("
  SELECT p.price, c.quantity
  FROM products p
  LEFT JOIN cart c ON c.product_id=p.id AND c.user_id=?
  WHERE p.id=?");
$stm->execute([$userId, $productId]);
$row = $stm->fetch();
$line_qty   = (int)($row['quantity'] ?? 0); // จำนวนล่าสุด
$unit_price = (float)($row['price'] ?? 0);  // ราคาต่อชิ้น
$line_total = $line_qty * $unit_price;    // ยอดรวมของสินค้านี้

// จำนวนชิ้นรวมใน badge (รวมตาม quantity)
$cartCount = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id={$userId}")->fetchColumn();
// ✅ ส่งข้อมูลกลับไปให้ JavaScript เพื่ออัปเดต UI
echo json_encode([
    'qty'         => $line_qty,
    'line_total'  => $line_total,
    'cart_count'  => $cartCount,
]);
