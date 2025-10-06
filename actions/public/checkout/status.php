<?php
require_once __DIR__ . '/_bootstrap.php';

$code = (string)($_GET['code'] ?? '');
if ($code === '') json_out(['error'=>'MISSING_CODE'], 400);

// pedido
$stO = $pdo->prepare("SELECT * FROM orders WHERE code = ? LIMIT 1");
$stO->execute([$code]);
$o = $stO->fetch(PDO::FETCH_ASSOC);
if (!$o) json_out(['error'=>'NOT_FOUND'], 404);
$orderId = (int)$o['id'];

// itens
$stI = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stI->execute([$orderId]);
$rows = $stI->fetchAll(PDO::FETCH_ASSOC);

$items = [];
$eta = 0;

$stP = $pdo->prepare("SELECT prep_time_min, name FROM products WHERE id = ? LIMIT 1");
$stE = $pdo->prepare("SELECT extra_id, extra_price FROM order_item_extras WHERE order_item_id = ?");
$stENames = $pdo->prepare("SELECT name FROM extras WHERE id = ? LIMIT 1");

foreach ($rows as $r) {
  $stP->execute([(int)$r['product_id']]);
  $prod = $stP->fetch(PDO::FETCH_ASSOC) ?: ['prep_time_min'=>0,'name'=>'Produto'];

  $extras = [];
  $extrasSum = 0.0;
  $stE->execute([(int)$r['id']]);
  foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $ex) {
    $stENames->execute([(int)$ex['extra_id']]);
    $name = $stENames->fetch(PDO::FETCH_ASSOC)['name'] ?? ('Extra #'.$ex['extra_id']);
    $price = (float)$ex['extra_price'];
    $extras[] = ['name'=>$name, 'price'=>$price];
    $extrasSum += $price;
  }

  $qty = (int)$r['qty'];
  $unit = (float)$r['unit_price'];
  $itemTotal = $qty * ($unit + $extrasSum);

  $items[] = [
    'name'       => $prod['name'],
    'qty'        => $qty,
    'unit_price' => $unit,
    'extras'     => $extras,
    'item_total' => round($itemTotal, 2)
  ];

  $eta += max(0, (int)$prod['prep_time_min']) * $qty;
}

// timeline
$timeline = [];
$lg = $pdo->prepare("SELECT from_status, to_status, changed_at FROM order_logs WHERE order_id = ? ORDER BY id ASC");
$lg->execute([$orderId]);
foreach ($lg->fetchAll(PDO::FETCH_ASSOC) as $l) {
  $timeline[] = ['from'=>$l['from_status'], 'to'=>$l['to_status'], 'at'=>$l['changed_at']];
}

json_out([
  'order_id' => $orderId,
  'code'     => $o['code'],
  'status'   => $o['status'],
  'timeline' => $timeline,
  'items'    => $items,
  'totals'   => ['total' => (float)$o['total'], 'currency'=>'BRL'],
  'eta_min'  => $eta,
  'updated_at' => $o['created_at']
]);
