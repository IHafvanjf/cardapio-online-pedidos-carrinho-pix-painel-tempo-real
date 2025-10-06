<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) json_error('ID inválido.');

$pdo->prepare("DELETE FROM products WHERE id=:id")->execute([':id'=>$id]);
json_ok([], 'Produto removido');
