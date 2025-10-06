<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
only_post();

$json = $_POST['json'] ?? '';
if (!$json) json_error('JSON ausente');
$data = json_decode($json, true);
if (!$data || !isset($data['products'])) json_error('Formato inválido');

$pdo->beginTransaction();
try {
  // simples: limpa tabelas principais (opcional, comente se não quiser)
  $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
  $pdo->exec("TRUNCATE TABLE product_images");
  $pdo->exec("TRUNCATE TABLE product_extra");
  $pdo->exec("TRUNCATE TABLE product_ingredient");
  $pdo->exec("TRUNCATE TABLE products");
  $pdo->exec("TRUNCATE TABLE ingredients");
  $pdo->exec("TRUNCATE TABLE extras");
  // categorias não são recriadas aqui (mantemos as 4 padrões)
  $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

  // re-insere ingredientes
  $mapIng = [];
  foreach ($data['ingredients'] ?? [] as $i) {
    $pdo->prepare("INSERT INTO ingredients(name, ativo) VALUES (:n,:a)")
        ->execute([':n'=>$i['name'], ':a'=> (int)$i['active']]);
    $mapIng[$i['id']] = (int)$pdo->lastInsertId();
  }

  // re-insere extras
  $mapEx = [];
  foreach ($data['extras'] ?? [] as $e) {
    $pdo->prepare("INSERT INTO extras(name, price, ativo, ordem) VALUES (:n,:p,:a,0)")
        ->execute([':n'=>$e['name'], ':p'=>$e['price'], ':a'=> (int)$e['active']]);
    $mapEx[$e['id']] = (int)$pdo->lastInsertId();
  }

  // produtos
  foreach ($data['products'] as $p) {
    $c = $pdo->prepare("SELECT id FROM categories WHERE slug=:s")->execute([':s'=>$p['category']]);
    $cid = $pdo->query("SELECT id FROM categories WHERE slug='".$p['category']."'")->fetchColumn();
    if (!$cid) continue;

    $pdo->prepare("INSERT INTO products(category_id,name,description,price,prep_time_min,ativo,destaque,ordem)
      VALUES (:c,:n,:d,:p,0,:a,0,0)")
      ->execute([':c'=>$cid, ':n'=>$p['name'], ':d'=>$p['desc'], ':p'=>$p['price'], ':a'=> (int)$p['active']]);
    $newId = (int)$pdo->lastInsertId();

    // N:N
    $qi = $pdo->prepare("INSERT INTO product_ingredient (product_id, ingredient_id) VALUES (:p,:i)");
    foreach ($p['ingredients'] ?? [] as $oldIng) if (isset($mapIng[$oldIng])) $qi->execute([':p'=>$newId, ':i'=>$mapIng[$oldIng]]);
    $qe = $pdo->prepare("INSERT INTO product_extra (product_id, extra_id) VALUES (:p,:e)");
    foreach ($p['extras'] ?? [] as $oldEx)  if (isset($mapEx[$oldEx]))  $qe->execute([':p'=>$newId, ':e'=>$mapEx[$oldEx]]);
  }

  $pdo->commit();
  json_ok([], 'Importado com sucesso');

} catch(Throwable $e) {
  $pdo->rollBack();
  json_error('Falha ao importar');
}
