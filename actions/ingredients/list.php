<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_get();

$q = trim($_GET['q'] ?? '');

$sql = "
  SELECT MIN(id) AS id, MIN(name) AS name, MAX(ativo) AS ativo
  FROM ingredients
";
$params = [];
$having = [];
if ($q !== '') {
  $having[] = "LOWER(MIN(name)) LIKE :q";
  $params[':q'] = '%'.mb_strtolower($q).'%';
}
$sql .= " GROUP BY LOWER(name) ";
if ($having) $sql .= " HAVING ".implode(' AND ', $having);
$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
json_ok(['ingredients'=>$stmt->fetchAll()]);
