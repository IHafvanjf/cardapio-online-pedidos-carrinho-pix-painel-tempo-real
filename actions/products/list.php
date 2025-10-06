<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_get();

$catSlug = trim($_GET['category'] ?? '');  // opcional: hamburguers/combos/bebidas/sobremesas
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(10, (int)($_GET['perPage'] ?? 50)));
$off     = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($catSlug !== '') {
  $where[] = "c.slug = :slug";
  $params[':slug'] = $catSlug;
}
if ($q !== '') {
  $where[] = "(p.name LIKE :q OR p.description LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$sqlWhere = $where ? ' WHERE '.implode(' AND ', $where) : '';

$sql = "
SELECT
  p.id, p.name, p.description, p.price, p.prep_time_min, p.ativo, p.destaque, p.ordem,
  c.slug AS category,
  (SELECT path FROM product_images WHERE product_id=p.id AND is_cover=1 LIMIT 1) AS cover_path
FROM products p
JOIN categories c ON c.id = p.category_id
$sqlWhere
ORDER BY p.ordem ASC, p.created_at DESC
LIMIT :off, :pp";

$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':off', $off, PDO::PARAM_INT);
$stmt->bindValue(':pp',  $perPage, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

/* carrega relações N:N separadas para montar arrays de IDs (compatível com seu JS) */
$ids = array_column($rows, 'id');
$ingredientsMap = [];
$extrasMap      = [];
if ($ids) {
  $in = implode(',', array_map('intval', $ids));

  // ingredientes do produto (DISTINCT evita repetição)
  $q1 = $pdo->query("
    SELECT DISTINCT product_id, ingredient_id
    FROM product_ingredient
    WHERE product_id IN ($in)
  ");
  foreach ($q1->fetchAll() as $r) {
    $ingredientsMap[$r['product_id']][] = (int)$r['ingredient_id'];
  }

  // extras do produto
  $q2 = $pdo->query("
    SELECT DISTINCT product_id, extra_id
    FROM product_extra
    WHERE product_id IN ($in)
  ");
  foreach ($q2->fetchAll() as $r) {
    $extrasMap[$r['product_id']][] = (int)$r['extra_id'];
  }
}

$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // /actions/products
$uploadsPrefix = $baseUrl . '/../uploads/';              // relativo

$items = array_map(function($r) use ($ingredientsMap, $extrasMap, $uploadsPrefix) {
  return [
    'id'          => (int)$r['id'],
    'name'        => $r['name'],
    'category'    => $r['category'],           // slug
    'price'       => (float)$r['price'],
    'desc'        => $r['description'],
    'img'         => $r['cover_path'] ? ($uploadsPrefix . $r['cover_path']) : '',
    'active'      => (bool)$r['ativo'],
    'ingredients' => $ingredientsMap[$r['id']] ?? [],
    'extras'      => $extrasMap[$r['id']] ?? []
  ];
}, $rows);

json_ok([
  'items'    => $items,
  'page'     => $page,
  'perPage'  => $perPage
]);
