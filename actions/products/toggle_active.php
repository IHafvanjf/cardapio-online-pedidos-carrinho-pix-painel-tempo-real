<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
$val = isset($_POST['active']) ? (int)!!$_POST['active'] : null;
if ($id<=0 || $val===null) json_error('Parâmetros inválidos.');

$pdo->prepare("UPDATE products SET ativo=:a WHERE id=:id")->execute([':a'=>$val, ':id'=>$id]);
json_ok([], 'Status atualizado');
