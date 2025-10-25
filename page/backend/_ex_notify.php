<?php
// /page/backend/_ex_notify.php
require_once __DIR__ . '/ex__common.php'; // มี dbx(), me(), jerr()

/** บันทึกแจ้งเตือนแบบ very simple: type = request.accepted | request.declined */
function ex_notify(int $toUid, string $type, string $text, array $payload = []): bool {
  $mysqli = dbx();

  $mysqli->query("
    CREATE TABLE IF NOT EXISTS ex_notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      type VARCHAR(32) NOT NULL,  -- request.accepted | request.declined
      text VARCHAR(255) NOT NULL,
      payload JSON NULL,
      is_read TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY (user_id),
      KEY (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $st = $mysqli->prepare("INSERT INTO ex_notifications(user_id,type,text,payload) VALUES(?,?,?,?)");
  $p  = json_encode($payload, JSON_UNESCAPED_UNICODE);
  $st->bind_param("isss", $toUid, $type, $text, $p);
  return $st->execute();
}
