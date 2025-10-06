<?php
declare(strict_types=1);

/* ====== HEADERS ====== */
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/* ====== DB include (caminho robusto) ====== */
$here = __DIR__;
$candidates = [
  dirname($here, 3) . '/includes/db.php',   // .../cardapio/includes/db.php
  dirname($here, 2) . '/includes/db.php',   // .../actions/includes/db.php (se você preferir)
];
$dbfile = null;
foreach ($candidates as $p) if (is_file($p)) { $dbfile = $p; break; }
if (!$dbfile) { http_response_code(500); echo json_encode(['error'=>'DB_FILE_NOT_FOUND']); exit; }
require_once $dbfile;

try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

function json_out($arr, int $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function read_json(): array { $raw=file_get_contents('php://input')?:''; $d=json_decode($raw,true); return is_array($d)?$d:[]; }
function to_cents($v): int { return (int) round(((float)$v)*100); }
function from_cents($c): float { return round(((int)$c)/100, 2); }

/** Gera código único tipo #361949 */
function gen_order_code(PDO $pdo): string {
  do {
    $code = '#' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $st = $pdo->prepare("SELECT 1 FROM orders WHERE code=?");
    $st->execute([$code]);
    $exists = (bool)$st->fetchColumn();
  } while ($exists);
  return $code;
}

/**
 * Reprecifica os itens do carrinho.
 * Espera: items = [{product_id, qty, extras: [extra_id,...]}]
 * Valida: produto/categoria ativos; extra ativo e permitido para o produto.
 */
function reprice_cart(PDO $pdo, array $items): array {
  if (!is_array($items) || !count($items)) json_out(['error'=>'EMPTY_CART'], 422);

  $outItems = [];
  $subtotal = 0;
  $etaMin   = 0;

  // statements preparados
  $stProd = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.prep_time_min
    FROM products p
    JOIN categories c ON c.id = p.category_id AND c.ativo=1
    WHERE p.id=? AND p.ativo=1
    LIMIT 1
  ");
  $stExtra = $pdo->prepare("
    SELECT e.id, e.name, e.price
    FROM extras e
    JOIN product_extra pe ON pe.extra_id = e.id AND pe.product_id = ?
    WHERE e.id=? AND e.ativo=1
    LIMIT 1
  ");

  foreach ($items as $row) {
    $pid = (int)($row['product_id'] ?? 0);
    $qty = max(1, (int)($row['qty'] ?? 1));
    $ext = is_array($row['extras'] ?? []) ? $row['extras'] : [];

    // produto
    $stProd->execute([$pid]);
    $p = $stProd->fetch(PDO::FETCH_ASSOC);
    if (!$p) json_out(['error'=>'PRODUCT_NOT_AVAILABLE','product_id'=>$pid], 422);

    // extras
    $extrasOk = [];
    $extrasTotal = 0;
    foreach ($ext as $eidRaw) {
      $eid = (int)$eidRaw;
      $stExtra->execute([$pid, $eid]);
      $e = $stExtra->fetch(PDO::FETCH_ASSOC);
      if (!$e) json_out(['error'=>'EXTRA_NOT_AVAILABLE','product_id'=>$pid,'extra_id'=>$eid], 422);
      $extrasOk[] = ['extra_id'=>(int)$e['id'], 'name'=>$e['name'], 'price'=>(float)$e['price']];
      $extrasTotal += to_cents($e['price']);
    }

    $unit = to_cents($p['price']);
    $itemTotal = ($unit + $extrasTotal) * $qty;

    $etaMin += max(0, (int)$p['prep_time_min']) * $qty;
    $subtotal += $itemTotal;

    $outItems[] = [
      'product_id'  => (int)$p['id'],
      'name'        => $p['name'],
      'qty'         => $qty,
      'unit_price'  => from_cents($unit),            // sem extras
      'extras'      => $extrasOk,                     // [{extra_id, name, price}]
      'item_total'  => from_cents($itemTotal)        // com extras * qty
    ];
  }

  return [
    'items'          => $outItems,
    'subtotal_cents' => $subtotal,
    'totals'         => ['total' => from_cents($subtotal), 'currency'=>'BRL'],
    'eta_min'        => $etaMin
  ];
}
