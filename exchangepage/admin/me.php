<?php
require __DIR__ . '/_config_admin.php';
$aid = require_admin();
$csrf = ensure_csrf_token();
json_ok(['admin_id'=>$aid,'csrf'=>$csrf]);
