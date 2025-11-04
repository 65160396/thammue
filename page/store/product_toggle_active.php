<?php
// /page/store/product_toggle_active.php
session_start();
require __DIR__ . '/../backend/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method not allowed']);
        exit;
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
 // ✅ รับค่าจากฟอร์ม (id สินค้า + id ร้าน)
    $pid = (int)($_POST['product_id'] ?? 0);
    $sid = (int)($_POST['shop_id'] ?? 0);
    $uid = (int)$_SESSION['user_id'];
    if ($pid <= 0 || $sid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad request']);
        exit;
    }

    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ตรวจสิทธิ์เจ้าของร้าน + ดึงสถานะปัจจุบัน
    $chk = $pdo->prepare("
        SELECT p.is_active
        FROM products p
        JOIN shops s ON s.id = p.shop_id
        WHERE p.id = ? AND p.shop_id = ? AND s.user_id = ?
        LIMIT 1
    ");
    $chk->execute([$pid, $sid, $uid]);
    $r = $chk->fetch();
    if (!$r) {
         // ❌ ถ้าไม่ใช่เจ้าของร้าน → ห้ามแก้ไข
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    // ✅ สลับสถานะ is_active (1 = เปิดขาย, 0 = ปิดขาย)
    $current = (int)$r['is_active'];
    $new     = $current ? 0 : 1;

    // อัปเดตแบบเจาะจงร้านด้วย
    $u = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ? AND shop_id = ?");
    $u->execute([$new, $pid, $sid]);
// ✅ ส่งผลลัพธ์กลับให้ Frontend ทราบสถานะใหม่
    echo json_encode(['ok' => true, 'is_active' => $new]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
