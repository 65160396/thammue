<?php
require __DIR__ . '/_config_admin.php';
start_admin_session(); session_destroy(); json_ok();
