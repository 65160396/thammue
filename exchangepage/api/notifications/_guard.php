<?php
require_once __DIR__ . '/../_config.php';
$mid = me_id();
if (!$mid) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
function me(){ global $mid; return (int)$mid; }

