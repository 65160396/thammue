<?php
// /page/backend/admin/shops_list.php
require_once __DIR__ . '/require_admin.php';
$pdo = admin_db();
require_admin();

$st = $pdo->query("
  SELECT s.id, s.user_id, s.shop_name, s.email, s.phone, s.pickup_addr, s.province, s.status, s.created_at, s.updated_at,
         v.seller_type, v.citizen_name, v.citizen_id, v.company_name, v.tax_id, v.dob, v.addr_line, v.subdistrict, v.district, v.province AS v_province, v.postcode,
         v.reg_doc, v.id_rep, v.status AS verify_status, v.created_at AS verify_created_at
  FROM shops s
  LEFT JOIN shop_verifications v ON v.shop_id = s.id
  WHERE s.status='pending'
  ORDER BY s.created_at ASC
");
$rows = $st->fetchAll();

echo json_encode(['ok'=>true, 'shops'=>$rows], JSON_UNESCAPED_UNICODE);
