<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) json_error('ID inválido.');

$pdo->beginTransaction();
try {
  $p = $pdo->prepare("SELECT * FROM products WHERE id=:id");
  $p->execute([':id'=>$id]);
  $prod = $p->fetch();
  if (!$prod) throw new Exception('Produto não encontrado');

  // clona produto
  $pdo->prepare("INSERT INTO products
    (category_id,name,description,price,prep_time_min,ativo,destaque,ordem)
    VALUES (:c, CONCAT(:n,' (cópia)'), :d,:p,0,ativo,destaque,ordem)")
     ->execute([
       ':c'=>$prod['category_id'],
       ':n'=>$prod['name'],
       ':d'=>$prod['description'],
       ':p'=>$prod['price']
     ]);
  $newId = (int)$pdo->lastInsertId();

  // clona ingredientes
  $rs = $pdo->prepare("SELECT ingredient_id FROM product_ingredient WHERE product_id=:p");
  $rs->execute([':p'=>$id]);
  $qi = $pdo->prepare("INSERT INTO product_ingredient (product_id, ingredient_id) VALUES (:p,:i)");
  foreach ($rs->fetchAll() as $r) $qi->execute([':p'=>$newId, ':i'=>$r['ingredient_id']]);

  // clona extras
  $re = $pdo->prepare("SELECT extra_id FROM product_extra WHERE product_id=:p");
  $re->execute([':p'=>$id]);
  $qe = $pdo->prepare("INSERT INTO product_extra (product_id, extra_id) VALUES (:p,:e)");
  foreach ($re->fetchAll() as $r) $qe->execute([':p'=>$newId, ':e'=>$r['extra_id']]);

  // clona capa (opcional)
  $img = $pdo->prepare("SELECT path,is_cover FROM product_images WHERE product_id=:p AND is_cover=1 LIMIT 1");
  $img->execute([':p'=>$id]);
  if ($row = $img->fetch()) {
    $pdo->prepare("INSERT INTO product_images (product_id,path,is_cover) VALUES (:p,:path,1)")
        ->execute([':p'=>$newId, ':path'=>$row['path']]);
  }

  $pdo->commit();
  json_ok(['id'=>$newId], 'Produto duplicado');

} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('Falha ao duplicar produto');
}
