<?php
require __DIR__ . '/../../bootstrap_pdo.php'; // ไฟล์เชื่อม DB ของคุณ

$sql = "
UPDATE orders
SET status='cancelled',
    cancelled_at=NOW(),
    cancel_reason='Auto-cancel: payment timeout'
WHERE status IN ('pending_payment','cod_pending')
  AND paid_at IS NULL
  AND payment_deadline < NOW()
";
$pdo->exec($sql);
