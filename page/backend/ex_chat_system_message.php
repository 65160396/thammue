<?php
// /page/backend/ex_chat_system_message.php
$REQUIRE_LOGIN = true;
require_once __DIR__ . '/ex__items_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

jok(['ok'=>true, 'note'=>'stub endpoint; wire to your chat table when ready']);
