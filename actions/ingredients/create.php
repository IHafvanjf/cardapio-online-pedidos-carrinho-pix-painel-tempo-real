<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$name = trim($_POST['name'] ?? '');
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;
if ($name==='') json_error('Nome obrigatório');

$pdo->prepare("INSERT INTO ingredients(name, ativo) VALUES (:n,:a)")
    ->execute([':n'=>$name, ':a'=>$active]);

json_ok(['id'=>$pdo->lastInsertId()], 'Ingrediente criado');
