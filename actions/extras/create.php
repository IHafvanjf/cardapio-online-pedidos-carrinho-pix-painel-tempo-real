<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$name = trim($_POST['name'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;
if ($name==='') json_error('Nome obrigatório');

$pdo->prepare("INSERT INTO extras(name, price, ativo, ordem) VALUES (:n,:p,:a,0)")
    ->execute([':n'=>$name, ':p'=>$price, ':a'=>$active]);

json_ok(['id'=>$pdo->lastInsertId()], 'Extra criado');
