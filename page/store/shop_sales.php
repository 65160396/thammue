<?php
header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = new PDO("mysql:host=localhost;dbname=shopdb;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $shopId = (int)($_GET['shop_id'] ?? 0);
  if ($shopId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing shop_id']);
    exit;
  }

  // ==== ช่วงเวลา & การรวม ====
  $group = $_GET['group'] ?? 'day';                 // day|month|year
  $from  = $_GET['from']  ?? null;                  // YYYY-MM-DD
  $to    = $_GET['to']    ?? null;                  // YYYY-MM-DD

  // ค่าปริยาย: 30 วันล่าสุด
  if (!$from || !$to) {
    $to   = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-30 days'));
  }

   // ✅ นิยาม expression สำหรับ group by ตามที่ขอ
  if ($group === 'week') {
    // กลุ่มตามปี+สัปดาห์ เช่น 2025-W42
    $gbExpr = "DATE_FORMAT(COALESCE(o.paid_at, o.created_at), '%x-W%v')";
  } elseif ($group === 'month') {
    $gbExpr = "DATE_FORMAT(COALESCE(o.paid_at, o.created_at), '%Y-%m')";
  } elseif ($group === 'year') {
    $gbExpr = "DATE_FORMAT(COALESCE(o.paid_at, o.created_at), '%Y')";
  } else {
    $gbExpr = "DATE(COALESCE(o.paid_at, o.created_at))";
    $group = 'day';
  }

  // ✅ นิยาม “ออเดอร์สำเร็จ” (paid/shipped หรือมี paid_at)
  $successCond = "(o.status IN ('paid','shipped') OR o.paid_at IS NOT NULL)";

  // ==== สรุปช่วงที่เลือก ====
  $q1 = $pdo->prepare("
    SELECT
      COUNT(DISTINCT o.id)                                                AS total_orders,
      SUM(oi.qty)                                                         AS total_items_sold,
      SUM(oi.qty * oi.price)                                              AS total_revenue,
      SUM(CASE WHEN o.pay_method='qr'  THEN oi.qty*oi.price ELSE 0 END)  AS qr_revenue,
      SUM(CASE WHEN o.pay_method='cod' THEN oi.qty*oi.price ELSE 0 END)  AS cod_revenue
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products     p ON p.id       = oi.product_id
    WHERE p.shop_id = :sid
      AND $successCond
      AND COALESCE(o.paid_at, o.created_at) BETWEEN :from AND :to
  ");
  $q1->execute([':sid' => $shopId, ':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
  $sum = $q1->fetch() ?: ['total_orders' => 0, 'total_items_sold' => 0, 'total_revenue' => 0, 'qr_revenue' => 0, 'cod_revenue' => 0];
  // ✅ ค่าเฉลี่ยรายออเดอร์
  $avgPerOrder = ($sum['total_orders'] ?? 0) ? (float)$sum['total_revenue'] / (int)$sum['total_orders'] : 0;

  // ยกเลิกในช่วง
  $qCancel = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) AS cancelled_orders
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p ON p.id = oi.product_id
    WHERE p.shop_id=:sid AND o.status='cancelled'
      AND COALESCE(o.paid_at, o.created_at) BETWEEN :from AND :to
  ");
  $qCancel->execute([':sid' => $shopId, ':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
  $cancel = $qCancel->fetch() ?: ['cancelled_orders' => 0];
  // rate คิดเทียบออเดอร์สำเร็จ+ยกเลิกในช่วง
  $baseOrders = ($sum['total_orders'] ?? 0) + ($cancel['cancelled_orders'] ?? 0);
  $cancelRate = $baseOrders > 0 ? round(100.0 * ($cancel['cancelled_orders'] ?? 0) / $baseOrders, 2) : 0.0;

  // จำนวนสินค้าในร้าน (ทั้งหมด ไม่ขึ้นกับช่วง)
  $qProd = $pdo->prepare("SELECT COUNT(*) AS product_count FROM products WHERE shop_id=:sid");
  $qProd->execute([':sid' => $shopId]);
  $prod = $qProd->fetch() ?: ['product_count' => 0];

  // Top สินค้า (ในช่วง)
  $q2 = $pdo->prepare("
    SELECT p.id, p.name, SUM(oi.qty) AS sold_qty, SUM(oi.qty * oi.price) AS revenue
    FROM order_items oi
    JOIN orders  o ON o.id  = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE p.shop_id=:sid AND $successCond
      AND COALESCE(o.paid_at, o.created_at) BETWEEN :from AND :to
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 100
  ");
  $q2->execute([':sid' => $shopId, ':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
  $topItems = $q2->fetchAll();

  // Timeseries ตาม group ที่เลือก
  $qTs = $pdo->prepare("
    SELECT
      $gbExpr AS bucket,
      SUM(oi.qty * oi.price) AS revenue,
      COUNT(DISTINCT o.id)   AS orders,
      SUM(oi.qty)            AS items
    FROM order_items oi
    JOIN orders  o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE p.shop_id = :sid AND $successCond
      AND COALESCE(o.paid_at, o.created_at) BETWEEN :from AND :to
    GROUP BY bucket
    ORDER BY bucket
  ");
  $qTs->execute([':sid' => $shopId, ':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
  $timeseries = $qTs->fetchAll();
 // ✅ breakdown รายได้ตามวิธีจ่าย + สัดส่วน (เปอร์เซ็นต์)
  $qr  = (float)($sum['qr_revenue']  ?? 0);
  $cod = (float)($sum['cod_revenue'] ?? 0);
  $totalPay = $qr + $cod;
  $payment = [
    'qr_revenue'  => $qr,
    'cod_revenue' => $cod,
    'qr_pct'      => $totalPay > 0 ? round($qr  * 100 / $totalPay, 2) : 0,
    'cod_pct'     => $totalPay > 0 ? round($cod * 100 / $totalPay, 2) : 0,
  ];
  // ✅ ส่งผลลัพธ์เป็น JSON สำหรับแดชบอร์ดร้าน
  echo json_encode([
    'ok' => true,
    'params' => ['from' => $from, 'to' => $to, 'group' => $group],
    'summary' => $sum,
    'avg_per_order' => $avgPerOrder,
    'cancelled_orders' => (int)$cancel['cancelled_orders'],
    'cancel_rate' => $cancelRate,
    'product_count' => (int)$prod['product_count'],
    'payment_breakdown' => $payment,
    'top_items' => $topItems,
    'timeseries' => $timeseries
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
