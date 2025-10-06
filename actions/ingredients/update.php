<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;
if ($id<=0 || $name==='') json_error('Dados inválidos');

$pdo->prepare("UPDATE ingredients SET name=:n, ativo=:a WHERE id=:id")
    ->execute([':n'=>$name, ':a'=>$active, ':id'=>$id]);

json_ok([], 'Ingrediente atualizado');
