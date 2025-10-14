<?php
session_start();
require __DIR__ . '/../../bootstrap_pdo.php';

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['id'] ?? 0);

$sql = "UPDATE orders
        SET status='cancelled', cancelled_at=NOW(), cancel_reason='User cancel'
        WHERE id=? AND user_id=? 
          AND status IN ('pending_payment','cod_pending')
          AND paid_at IS NULL
          AND payment_deadline > NOW()";
$ok = $pdo->prepare($sql)->execute([$orderId, $userId]);

header('Location: /page/orders/view.php?id=' . $orderId);
exit;
