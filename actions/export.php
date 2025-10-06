<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
only_get();

$cats = $pdo->query("SELECT id, name, slug, ativo, ordem FROM categories ORDER BY ordem, name")->fetchAll();
$ings = $pdo->query("SELECT id, name, ativo FROM ingredients ORDER BY name")->fetchAll();
$extras = $pdo->query("SELECT id, name, price, ativo, ordem FROM extras ORDER BY name, price")->fetchAll();
$prods = $pdo->query("
  SELECT p.id, p.name, p.description, p.price, p.ativo,
         c.slug AS category,
         (SELECT path FROM product_images WHERE product_id=p.id AND is_cover=1 LIMIT 1) AS cover_path
  FROM products p JOIN categories c ON c.id=p.category_id
  ORDER BY p.id DESC
")->fetchAll();

$mapIng = []; $mapEx = [];
if ($prods) {
  $ids = implode(',', array_map('intval', array_column($prods, 'id')));
  foreach ($pdo->query("SELECT DISTINCT product_id, ingredient_id FROM product_ingredient WHERE product_id IN ($ids)") as $r) {
    $mapIng[$r['product_id']][] = (int)$r['ingredient_id'];
  }
  foreach ($pdo->query("SELECT DISTINCT product_id, extra_id FROM product_extra WHERE product_id IN ($ids)") as $r) {
    $mapEx[$r['product_id']][] = (int)$r['extra_id'];
  }
}
$data = ['categories'=>$cats,'ingredients'=>$ings,'extras'=>$extras,'products'=>[]];
foreach ($prods as $p) {
  $data['products'][] = [
    'id'=>(int)$p['id'], 'name'=>$p['name'], 'desc'=>$p['description'], 'price'=>(float)$p['price'],
    'active'=> (bool)$p['ativo'], 'category'=>$p['category'],
    'img'=>$p['cover_path'] ? ('actions/uploads/'.$p['cover_path']) : '',
    'ingredients'=>$mapIng[$p['id']] ?? [], 'extras'=>$mapEx[$p['id']] ?? []
  ];
}
json_ok($data);
