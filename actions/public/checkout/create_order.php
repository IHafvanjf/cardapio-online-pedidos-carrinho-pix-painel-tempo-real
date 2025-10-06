<?php
declare(strict_types=1);

/**
 * BurgerHub • Checkout - create_order
 * - Reprecifica/valida itens
 * - Cria pedido + itens + extras
 * - Responde 201 com { order_id, code, status, total, eta_min, currency }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

function out($payload, int $code = 200) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    @file_put_contents(__DIR__.'/create_order_error.log',
      '['.date('c')."] FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n", FILE_APPEND);
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8', true, 500);
    }
    echo json_encode(['error'=>'SERVER_FATAL','message'=>'Erro interno ao criar pedido'], JSON_UNESCAPED_UNICODE);
  }
});

// ===== DB/config =====
$rootIncludes = dirname(__DIR__, 3).'/includes';
require_once $rootIncludes.'/db.php';      // $pdo

// ===== tenta usar _pricing_core.php (carregar ANTES de helpers locais) =====
$pricingCore = __DIR__.'/_pricing_core.php';
$haveCore = false;
if (is_file($pricingCore)) {
  require_once $pricingCore;   // pode declarar read_json, to_cents, from_cents, reprice_cart, etc.
  $haveCore = true;
}

// ===== helpers locais (só se não existirem) =====
if (!function_exists('read_json')) {
  function read_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
  }
}
if (!function_exists('from_cents')) {
  function from_cents(int $c): string { return number_format($c/100, 2, '.', ''); }
}
if (!function_exists('to_cents')) {
  function to_cents($brl): int { return (int)round(((float)$brl) * 100); }
}
if (!function_exists('gen_order_code')) {
  function gen_order_code(PDO $pdo): string {
    do {
      $code = '#'.random_int(100000, 999999);
      $st = $pdo->prepare('SELECT 1 FROM orders WHERE code=? LIMIT 1');
      $st->execute([$code]);
      $exists = (bool)$st->fetchColumn();
    } while ($exists);
    return $code;
  }
}
if (!function_exists('column_exists')) {
  function column_exists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch();
  }
}

// ===== reprecificação fallback (se core não prover) =====
if (!function_exists('reprice_cart')) {
  function reprice_cart(PDO $pdo, array $items): array {
    if (empty($items)) return ['items'=>[], 'totals'=>['total_cents'=>0], 'eta_min'=>0];

    // produtos
    $ids = [];
    foreach ($items as $it) {
      $pid = (int)($it['product_id'] ?? 0);
      if ($pid > 0) $ids[$pid] = true;
    }
    if (!$ids) out(['error'=>'EMPTY_CART'], 422);
    $idList = implode(',', array_map('intval', array_keys($ids)));

    $sqlP = "
      SELECT p.id, p.price, p.prep_time_min, p.ativo AS p_ok, c.ativo AS c_ok
      FROM products p
      JOIN categories c ON c.id = p.category_id
      WHERE p.id IN ($idList)
    ";
    $mapP = [];
    foreach ($pdo->query($sqlP) as $r) $mapP[(int)$r['id']] = $r;

    // extras permitidos por produto
    $sqlPE = "SELECT product_id, extra_id FROM product_extra WHERE product_id IN ($idList)";
    $allow = [];
    foreach ($pdo->query($sqlPE) as $r) $allow[(int)$r['product_id']][(int)$r['extra_id']] = true;

    // tabela de extras
    $mapE = [];
    foreach ($pdo->query("SELECT id, price, ativo FROM extras") as $r) $mapE[(int)$r['id']] = $r;

    $outItems = [];
    $totalCents = 0;
    $etaMin = 0;

    foreach ($items as $it) {
      $pid = (int)($it['product_id'] ?? 0);
      $qty = max(1, (int)($it['qty'] ?? 1));

      $P = $mapP[$pid] ?? null;
      if (!$P || !$P['p_ok'] || !$P['c_ok']) out(['error'=>'PRODUCT_NOT_AVAILABLE','product_id'=>$pid], 422);

      $unit = (float)$P['price'];
      $prep = (int)$P['prep_time_min'];

      $exArr = is_array($it['extras'] ?? null) ? $it['extras'] : [];
      $exRet = [];
      foreach ($exArr as $ex) {
        $eid = is_array($ex) ? (int)($ex['extra_id'] ?? 0) : (int)$ex;
        if ($eid<=0) continue;
        if (empty($allow[$pid][$eid])) out(['error'=>'EXTRA_NOT_ALLOWED','product_id'=>$pid,'extra_id'=>$eid], 422);
        $E = $mapE[$eid] ?? null;
        if (!$E || !$E['ativo']) out(['error'=>'EXTRA_NOT_AVAILABLE','extra_id'=>$eid], 422);
        $exPrice = (float)$E['price'];
        $unit += $exPrice;
        $exRet[] = ['extra_id'=>$eid, 'price'=>$exPrice];
      }

      $lineCents = to_cents($unit) * $qty;
      $totalCents += $lineCents;
      $etaMin += max(0, $prep) * $qty;

      $outItems[] = [
        'product_id'     => $pid,
        'qty'            => $qty,
        'unit_price'     => (float)from_cents(to_cents($unit)),
        'prep_time_min'  => $prep,
        'extras'         => $exRet,
      ];
    }

    return [
      'items'  => $outItems,
      'totals' => ['total_cents' => $totalCents],
      'eta_min'=> $etaMin,
    ];
  }
}

// ===== entrada =====
$body     = read_json();
$items    = $body['items'] ?? [];
$customer = (array)($body['customer'] ?? []);
if (!is_array($items) || count($items) === 0) out(['error'=>'EMPTY_CART'], 422);

$tableRef = (string)($customer['table_ref'] ?? '');
$obs      = (string)($customer['note'] ?? '');
$custName = (string)($customer['name'] ?? '');

// ===== reprecifica e valida =====
$r = reprice_cart($pdo, $items);

// total (aceita total_cents, totals.total_cents ou subtotal_cents)
$totalCents = (int)($r['totals']['total_cents'] ?? $r['total_cents'] ?? $r['subtotal_cents'] ?? 0);
if ($totalCents <= 0) out(['error'=>'INVALID_TOTAL','details'=>$r['totals'] ?? $r], 422);

// ETA
$etaMin = (int)($r['eta_min'] ?? 0);
if ($etaMin <= 0 && !empty($r['items']) && is_array($r['items'])) {
  $tmp = 0;
  foreach ($r['items'] as $it) $tmp += (int)($it['prep_time_min'] ?? 0) * (int)($it['qty'] ?? 1);
  $etaMin = $tmp;
}

// ===== grava =====
try {
  $pdo->beginTransaction();

  $code = gen_order_code($pdo);
  $hasEta = column_exists($pdo, 'orders', 'eta_min');

  if ($hasEta) {
    $st = $pdo->prepare("
      INSERT INTO orders (code, customer_name, table_ref, status, observations, total, eta_min, created_at)
      VALUES (?, ?, ?, 'aguardando', ?, ?, ?, NOW())
    ");
    $st->execute([$code, $custName, $tableRef, $obs, from_cents($totalCents), $etaMin]);
  } else {
    $st = $pdo->prepare("
      INSERT INTO orders (code, customer_name, table_ref, status, observations, total, created_at)
      VALUES (?, ?, ?, 'aguardando', ?, ?, NOW())
    ");
    $st->execute([$code, $custName, $tableRef, $obs, from_cents($totalCents)]);
  }
  $orderId = (int)$pdo->lastInsertId();

  $stItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, qty, unit_price, note)
    VALUES (?, ?, ?, ?, NULL)
  ");
  $stEx = $pdo->prepare("
    INSERT INTO order_item_extras (order_item_id, extra_id, extra_price)
    VALUES (?, ?, ?)
  ");

  foreach ((array)$r['items'] as $it) {
    $stItem->execute([
      $orderId,
      (int)$it['product_id'],
      (int)$it['qty'],
      (float)$it['unit_price'],
    ]);
    $orderItemId = (int)$pdo->lastInsertId();

    foreach ((array)($it['extras'] ?? []) as $ex) {
      $stEx->execute([
        $orderItemId,
        (int)$ex['extra_id'],
        (float)$ex['price'],
      ]);
    }
  }

  $pdo->prepare("
    INSERT INTO order_logs (order_id, from_status, to_status, changed_at)
    VALUES (?, NULL, 'aguardando', NOW())
  ")->execute([$orderId]);

  $pdo->commit();

  out([
    'order_id' => $orderId,
    'code'     => $code,
    'status'   => 'aguardando',
    'total'    => from_cents($totalCents),
    'eta_min'  => $etaMin,
    'currency' => 'BRL',
  ], 201);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  @file_put_contents(__DIR__.'/create_order_error.log',
    '['.date('c').'] EXCEPTION: '.$e->getMessage()."\n", FILE_APPEND);
  out(['error'=>'SERVER_ERROR','message'=>'Falha ao criar pedido'], 500);
}
