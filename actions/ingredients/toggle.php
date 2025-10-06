<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : null;
if ($id<=0 || $active===null) json_error('Parâmetros inválidos');

$pdo->prepare("UPDATE ingredients SET ativo=:a WHERE id=:id")->execute([':a'=>$active, ':id'=>$id]);
json_ok([], 'Status atualizado');
