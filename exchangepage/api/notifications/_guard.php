<?php
require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$mid = me_id();
if (!$mid) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }

function me(): int { global $mid; return (int)$mid; }
