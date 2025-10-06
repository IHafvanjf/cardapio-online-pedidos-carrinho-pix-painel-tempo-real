<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_get();

$q = trim($_GET['q'] ?? '');

$sql = "SELECT id, name, slug, ativo, ordem FROM categories";
$params = [];
if ($q !== '') {
  $sql .= " WHERE name LIKE :q OR slug LIKE :q";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY ordem ASC, name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
json_ok(['categories' => $stmt->fetchAll()]);
