<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;
if ($id<=0 || $name==='') json_error('Dados inválidos');

$pdo->prepare("UPDATE extras SET name=:n, price=:p, ativo=:a WHERE id=:id")
    ->execute([':n'=>$name, ':p'=>$price, ':a'=>$active, ':id'=>$id]);

json_ok([], 'Extra atualizado');
