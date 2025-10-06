<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) json_error('ID inválido');

$pdo->beginTransaction();
$pdo->prepare("DELETE FROM product_extra WHERE extra_id=:e")->execute([':e'=>$id]);
$pdo->prepare("DELETE FROM extras WHERE id=:e")->execute([':e'=>$id]);
$pdo->commit();

json_ok([], 'Extra removido');
