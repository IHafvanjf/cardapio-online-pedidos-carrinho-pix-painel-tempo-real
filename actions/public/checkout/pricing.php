<?php
require_once __DIR__ . '/_pricing_core.php';

$body  = read_json();
$items = $body['items'] ?? [];
$r = reprice_cart($pdo, $items);

json_out([
  'items'  => $r['items'],
  'totals' => [
    'subtotal' => from_cents($r['subtotal_cents']),
    'discounts'=> 0.00,
    'total'    => from_cents($r['subtotal_cents']),
    'currency' => 'BRL'
  ],
  'eta_min'  => $r['eta_min'],
  'quote_id' => 'q_' . bin2hex(random_bytes(3))
]);
