<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$name   = trim($_POST['name'] ?? '');
$cat    = trim($_POST['category'] ?? '');
$price  = (float)($_POST['price'] ?? 0);
$desc   = trim($_POST['desc'] ?? '');
$active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;

/* PREP: força 0 quando vier vazio e loga */
$prep = 0;
if (isset($_POST['prep_time_min']) && $_POST['prep_time_min'] !== '') {
  $prep = (int)$_POST['prep_time_min'];
}
error_log("[create] prep_time_min POST=".(isset($_POST['prep_time_min'])?var_export($_POST['prep_time_min'],true):'(missing)')." | usando=".$prep);

$ingredients = $_POST['ingredients'] ?? [];
$extras      = $_POST['extras'] ?? [];
$img_b64     = $_POST['img_b64'] ?? '';

if ($name === '' || $cat === '') json_error('Nome e categoria são obrigatórios.');

$stmt = $pdo->prepare("SELECT id FROM categories WHERE slug=:s AND ativo=1");
$stmt->execute([':s'=>$cat]);
$catRow = $stmt->fetch();
if (!$catRow) json_error('Categoria inválida.');

$pdo->beginTransaction();
try {
  $sql = "INSERT INTO products
            (category_id, name, description, price, prep_time_min, ativo, destaque, ordem)
          VALUES
            (:c,:n,:d,:p,:prep,:a,0,0)";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':c',    $catRow['id'], PDO::PARAM_INT);
  $stmt->bindValue(':n',    $name);
  $stmt->bindValue(':d',    $desc);
  $stmt->bindValue(':p',    $price);
  $stmt->bindValue(':prep', $prep, PDO::PARAM_INT);
  $stmt->bindValue(':a',    $active, PDO::PARAM_INT);
  $stmt->execute();

  $prodId = (int)$pdo->lastInsertId();

  if (!empty($ingredients) && is_array($ingredients)) {
    $qi = $pdo->prepare("INSERT INTO product_ingredient (product_id, ingredient_id) VALUES (:p,:i)");
    foreach ($ingredients as $iid) $qi->execute([':p'=>$prodId, ':i'=>(int)$iid]);
  }
  if (!empty($extras) && is_array($extras)) {
    $qe = $pdo->prepare("INSERT INTO product_extra (product_id, extra_id) VALUES (:p,:e)");
    foreach ($extras as $eid) $qe->execute([':p'=>$prodId, ':e'=>(int)$eid]);
  }

  if ($img_b64) {
    $path = save_base64_image($img_b64, $prodId);
    if ($path) {
      $pdo->prepare("UPDATE product_images SET is_cover=0 WHERE product_id=:p")->execute([':p'=>$prodId]);
      $pdo->prepare("INSERT INTO product_images (product_id, path, is_cover) VALUES (:p,:path,1)")
          ->execute([':p'=>$prodId, ':path'=>$path]);
    }
  }

  $pdo->commit();
  json_ok(['id'=>$prodId], 'Produto criado');

} catch (Throwable $e) {
  $pdo->rollBack();
  error_log("[create] erro: ".$e->getMessage());
  json_error('Falha ao criar produto');
}

function save_base64_image($b64, $productId) {
  if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $b64, $m)) return null;
  $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : (strtolower($m[1]) === 'jpg' ? 'jpg' : strtolower($m[1]));
  $data = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $b64));
  if (!$data) return null;
  $dir = __DIR__ . '/../uploads';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $fname = "prod_{$productId}_" . time() . "." . $ext;
  $full = $dir . '/' . $fname;
  if (file_put_contents($full, $data) === false) return null;
  return $fname;
}
