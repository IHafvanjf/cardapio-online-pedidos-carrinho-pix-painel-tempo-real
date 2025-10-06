<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$slug = trim($_POST['category'] ?? ''); // hamburgueres|combos|bebidas|sobremesas|todas
$pct  = (float)($_POST['percent'] ?? 0);

if ($slug==='') json_error('Categoria obrigatória');

if ($slug==='todas') {
  $sql = "UPDATE products SET price = ROUND(price * (1 + :pct/100), 2)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':pct'=>$pct]);
} else {
  $sql = "UPDATE products p JOIN categories c ON c.id=p.category_id
          SET p.price = ROUND(p.price * (1 + :pct/100), 2)
          WHERE c.slug=:s";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':pct'=>$pct, ':s'=>$slug]);
}
json_ok([], 'Ajuste aplicado');
