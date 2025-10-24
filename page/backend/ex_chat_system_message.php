<?php
// /page/backend/ex_chat_system_message.php
require_once __DIR__ . '/ex__common.php';

/** ตรวจว่าตารางมีอยู่ไหม */
function ex_table_exists(mysqli $m, string $table): bool {
  $db = $m->query("SELECT DATABASE()")->fetch_row()[0] ?? '';
  $st = $m->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?");
  $st->bind_param("ss", $db, $table);
  $st->execute();
  return (int)$st->get_result()->fetch_row()[0] > 0;
}

/** ส่ง system message เข้าแชท แล้วอัปเดต last_message_at */
function ex_send_system_message(mysqli $m, int $chat_id, string $body): bool {
  if ($chat_id <= 0 || $body === '') return false;

  $useChatMessages = ex_table_exists($m, 'ex_chat_messages');

  if ($useChatMessages) {
    // สคีมาตาม ex_chat_messages
    $sql = "INSERT INTO ex_chat_messages
              (chat_id, sender_id, recipient_id, body, is_system, meta_json, is_read, created_at)
            VALUES (?, NULL, NULL, ?, 1, NULL, 0, NOW())";
  } else {
    // fallback ให้ทำงานได้กับตาราง ex_messages แบบเก่า
    $sql = "INSERT INTO ex_messages (chat_id, sender_id, body, created_at)
            VALUES (?, NULL, ?, NOW())";
  }

  $st = $m->prepare($sql);
  if (!$st) { throw new Exception('prepare: '.$m->error); }
  if (!$st->bind_param("is", $chat_id, $body)) { throw new Exception('bind: '.$st->error); }
  if (!$st->execute()) { throw new Exception('execute: '.$st->error); }

  $m->query("UPDATE ex_chats SET last_message_at=NOW() WHERE id=".(int)$chat_id);
  return true;
}
