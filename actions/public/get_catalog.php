<?php
declare(strict_types=1);
// antes
// header('Cache-Control: max-age=60, public');

// depois (dev)
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', '0');
error_reporting(E_ALL);

function out($data, int $code=200){ http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail($msg, $detail=''){ out(['error'=>true,'message'=>$msg,'detail'=>$detail], 500); }

// -------- Conexão (PDO em includes/db.php) --------
$candidates = [
  dirname(__DIR__, 2) . '/includes/db.php',
  __DIR__ . '/../../includes/db.php',
  __DIR__ . '/../../../includes/db.php',
];
$incPath=null; foreach($candidates as $p){ if(file_exists($p)){ $incPath=$p; break; } }
if(!$incPath) fail('db.php não encontrado', implode(' | ', $candidates));
require_once $incPath;
if(!isset($pdo) || !($pdo instanceof PDO)) fail('PDO não disponível em $pdo');

// -------- Helpers --------
function norm(string $s): string {
  $s = mb_strtolower($s, 'UTF-8');
  $t = @iconv('UTF-8','ASCII//TRANSLIT',$s); if($t!==false) $s=$t;
  return preg_replace('/[^a-z0-9]+/','',$s);
}
function map_front_cat(?string $slug, ?string $name): ?string {
  $a = norm((string)$slug);
  $b = norm((string)$name);
  $hay = $a.' '.$b;

  // burgers
  if (str_contains($hay,'burger') || str_contains($hay,'hamburguer') || str_contains($hay,'hamburger')) return 'burgers';
  // combos
  if (str_contains($hay,'combo')) return 'combos';
  // drinks
  if (str_contains($hay,'bebida') || str_contains($hay,'drink') || str_contains($hay,'refri') || str_contains($hay,'suco') || str_contains($hay,'milkshake') || str_contains($hay,'shake')) return 'drinks';
  // desserts
  if (str_contains($hay,'sobremesa') || str_contains($hay,'dessert') || str_contains($hay,'postre') || str_contains($hay,'doce')) return 'desserts';

  return null; // categorias não exibidas no cardápio público
}

// -------- Params (apenas normalização) --------
$lang = strtolower($_GET['lang'] ?? 'pt');
if (str_starts_with($lang, 'pt')) $lang = 'pt';
elseif (str_starts_with($lang, 'en')) $lang = 'en';
elseif (str_starts_with($lang, 'es')) $lang = 'es';
else $lang = 'pt';

// -------- 1) Produtos ativos + categoria ativa + todos ingredientes ativos --------
try {
  $sql = "
    SELECT
      p.id,
      c.slug   AS category_slug,
      c.name   AS category_name,
      p.name   AS product_name,
      p.description,
      p.price,
      p.prep_time_min,
      (
        SELECT pi.path
        FROM product_images pi
        WHERE pi.product_id = p.id
        ORDER BY pi.is_cover DESC, pi.id ASC
        LIMIT 1
      ) AS img_path
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.ativo = 1
      AND c.ativo = 1
      AND NOT EXISTS (
        SELECT 1
        FROM product_ingredient pi
        JOIN ingredients i ON i.id = pi.ingredient_id
        WHERE pi.product_id = p.id
          AND (i.ativo IS NULL OR i.ativo = 0)
      )
    ORDER BY c.ordem ASC, p.destaque DESC, p.ordem ASC, p.id DESC
  ";
  $st = $pdo->query($sql);

  $products = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $frontCat = map_front_cat($r['category_slug'] ?? '', $r['category_name'] ?? '');
    if (!$frontCat) continue; // pula categorias não mapeadas pro cardápio

    $pid = (string)$r['id'];
    $products[$pid] = [
      'id'    => $pid,
      'cat'   => $frontCat,                     // mapeado p/ burgers|combos|drinks|desserts
      'img'   => $r['img_path'] ?: null,
      'price' => (float)$r['price'],
      'prep'  => (int)$r['prep_time_min'],
      'name'  => (string)$r['product_name'],
      'desc'  => (string)($r['description'] ?? ''),
      'extras'=> []
    ];
  }

  if (!$products) {
    out(['burgers'=>[], 'combos'=>[], 'drinks'=>[], 'desserts'=>[]]);
  }

  // -------- 2) Extras ativos por produto --------
  $ids = array_map('intval', array_keys($products));
  $ph  = implode(',', array_fill(0, count($ids), '?'));

  $sqlX = "
    SELECT
      pe.product_id,
      e.id    AS extra_id,
      e.name  AS extra_name,
      e.price AS extra_price
    FROM product_extra pe
    JOIN extras e ON e.id = pe.extra_id
    WHERE pe.product_id IN ($ph) AND e.ativo = 1
    ORDER BY e.ordem ASC, e.id ASC
  ";
  $sx = $pdo->prepare($sqlX);
  foreach ($ids as $i=>$v) $sx->bindValue($i+1, $v, PDO::PARAM_INT);
  $sx->execute();

  while ($x = $sx->fetch(PDO::FETCH_ASSOC)) {
    $pid = (string)$x['product_id'];
    if (!isset($products[$pid])) continue;
    $products[$pid]['extras'][] = [
      'k' => 'extra_' . (int)$x['extra_id'],
      'p' => (float)$x['extra_price'],
      'n' => (string)$x['extra_name']
    ];
  }

  // -------- 3) Agrupar no formato do front --------
  $out = ['burgers'=>[], 'combos'=>[], 'drinks'=>[], 'desserts'=>[]];
  foreach ($products as $p) {
    $out[$p['cat']][] = [
      'id'    => $p['id'],
      'img'   => $p['img'],
      'price' => $p['price'],
      'prep'  => $p['prep'],
      'name'  => $p['name'],
      'desc'  => $p['desc'],
      'extras'=> $p['extras']
    ];
  }

  out($out);
} catch (Throwable $e) {
  fail('Erro ao montar catálogo', $e->getMessage());
}
