<?php
require_once '../../includes/db.php';
require_once '../../includes/helpers.php';
only_post();

$id = (int)($_POST['id'] ?? 0);
$b64 = $_POST['img_b64'] ?? '';
if ($id<=0 || !$b64) json_error('Parâmetros inválidos.');

$path = save_base64_image($b64, $id);
if (!$path) json_error('Falha ao salvar imagem.');

$pdo->prepare("UPDATE product_images SET is_cover=0 WHERE product_id=:p")->execute([':p'=>$id]);
$pdo->prepare("INSERT INTO product_images (product_id, path, is_cover) VALUES (:p,:path,1)")
    ->execute([':p'=>$id, ':path'=>$path]);

json_ok(['path'=>$path], 'Imagem atualizada');

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
