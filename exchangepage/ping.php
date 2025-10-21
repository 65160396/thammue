<?php
require __DIR__ . '/_config.php';
json_ok(['msg' => 'pong', 'me' => me_id(), 'base' => THAMMUE_BASE]);
