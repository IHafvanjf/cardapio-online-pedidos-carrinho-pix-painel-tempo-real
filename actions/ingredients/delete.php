<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) json_error('ID inválido');

$pdo->beginTransaction();
$pdo->prepare("DELETE FROM product_ingredient WHERE ingredient_id=:i")->execute([':i'=>$id]);
$pdo->prepare("DELETE FROM ingredients WHERE id=:i")->execute([':i'=>$id]);
$pdo->commit();

json_ok([], 'Ingrediente removido');
