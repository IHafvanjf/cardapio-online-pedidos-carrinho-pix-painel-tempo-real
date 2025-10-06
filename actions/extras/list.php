<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_get();

$q = trim($_GET['q'] ?? '');

$sql = "
  SELECT MIN(id) AS id, MIN(name) AS name, price, MAX(ativo) AS ativo, MIN(ordem) AS ordem
  FROM extras
";
$params=[]; $having=[];
if ($q!==''){ $having[]="LOWER(MIN(name)) LIKE :q"; $params[':q']='%'.mb_strtolower($q).'%'; }
$sql .= " GROUP BY LOWER(name), price ";
if ($having) $sql .= " HAVING ".implode(' AND ', $having);
$sql .= " ORDER BY name ASC, price ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
json_ok(['extras'=>$stmt->fetchAll()]);
