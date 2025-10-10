<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php'; // path ถูกแล้ว

$slug  = $_GET['slug']  ?? '';
$limit = max(1, min(48, (int)($_GET['limit'] ?? 24)));

$idMap = [
    'handmade'       => 1,
    'craft'         => 2,
    'local_products' => 3,
    'second_hand'    => 4,
];

$catId = $idMap[$slug] ?? 0;
if ($catId === 0) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
  SELECT
    p.id,
    COALESCE(p.title, p.name, CONCAT('สินค้า #', p.id)) AS title,
    p.price,
    COALESCE(p.location, p.address, NULL) AS location,
    (
      SELECT i.image_path
      FROM product_images i
      WHERE i.product_id = p.id
      ORDER BY i.is_cover DESC, i.id ASC
      LIMIT 1
    ) AS cover_url
  FROM products p
  WHERE p.category_id = :cid
  ORDER BY p.id DESC
  LIMIT :limit
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':cid',   $catId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$items = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['cover_url'] = $row['cover_url'] ?: '/img/placeholder.png';
    $items[] = $row;
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
