<?php
require __DIR__ . '/../../bootstrap_pdo.php'; // ไฟล์เชื่อม DB ของคุณ
// ✅ คำสั่ง SQL สำหรับยกเลิกคำสั่งซื้อที่ยังไม่ได้ชำระภายในเวลาที่กำหนด
$sql = "
UPDATE orders
SET status='cancelled',                                   -- เปลี่ยนสถานะเป็น 'ยกเลิก'
    cancelled_at=NOW(),                                   -- บันทึกเวลาที่ยกเลิก
    cancel_reason='Auto-cancel: payment timeout'          -- เหตุผลการยกเลิกอัตโนมัติ
WHERE status IN ('pending_payment','cod_pending')         -- อยู่ระหว่างรอชำระ
  AND paid_at IS NULL                                     -- ยังไม่ได้ชำระจริง
  AND payment_deadline < NOW()                            -- เกินเวลาชำระแล้ว
";
$pdo->exec($sql);
