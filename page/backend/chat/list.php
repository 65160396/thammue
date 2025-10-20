<?php
// /page/backend/chat/list.php
require __DIR__ . '/_bootstrap.php';
$me = require_login();

try {
  $sql = "
SELECT
  c.id,
  CASE
    WHEN c.user_id=? THEN s.shop_name
    ELSE COALESCE(NULLIF(TRIM(u.name),''), CONCAT('ผู้ใช้ #', u.id))
  END AS other_name,
  p.name AS item_title,  -- << ใช้ products อย่างเดียว
  (SELECT m.body FROM shop_chat_messages m WHERE m.conv_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
  c.updated_at
FROM shop_chats c
LEFT JOIN shops  s ON s.id=c.shop_id
LEFT JOIN users  u ON u.id=c.user_id
LEFT JOIN products p ON p.id=c.item_id
WHERE c.user_id=? OR s.user_id=?
ORDER BY c.updated_at DESC, c.id DESC
LIMIT 50";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('iii', $me, $me, $me);
  $stmt->execute();
  $res = $stmt->get_result();
  $items = [];
  while ($row = $res->fetch_assoc()) {
    $items[] = $row;
  }
  $stmt->close();

  echo json_encode(['ok' => true, 'items' => $items]);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
