<?php
require_once __DIR__.'/_bootstrap.php';

$since = isset($_GET['since']) ? (string)$_GET['since'] : null;
$sinceSql = $since ? 'AND GREATEST(o.created_at, IFNULL(lc.last_change, o.created_at)) > ?' : '';

$lastChangeSQL = "
  SELECT ol.order_id, MAX(ol.changed_at) AS last_change
  FROM order_logs ol
  GROUP BY ol.order_id
";

/** ATIVOS **/
$sqlActive = "
  WITH last_change AS ($lastChangeSQL)
  SELECT o.id, o.code, o.table_ref, o.status, o.observations, o.created_at,
         o.eta_min, o.prep_started_at,
         lc.last_change
  FROM orders o
  LEFT JOIN last_change lc ON lc.order_id = o.id
  WHERE o.status IN ('aguardando','preparando')
  $sinceSql
  ORDER BY 
    FIELD(o.status,'preparando','aguardando'),   -- prepara primeiro
    o.created_at ASC, o.id ASC
";
$stA = $pdo->prepare($sqlActive);
if ($since) $stA->execute([$since]); else $stA->execute();
$activeRows = $stA->fetchAll(PDO::FETCH_ASSOC);

$itemsSQL = "
  SELECT oi.id AS order_item_id, oi.product_id, oi.qty, oi.unit_price,
         p.name AS product_name, p.prep_time_min
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id = ?
";
$extrasSQL = "
  SELECT e.name, oie.extra_price
  FROM order_item_extras oie
  JOIN extras e ON e.id = oie.extra_id
  WHERE oie.order_item_id = ?
";

$active = [];
$seq = 0;
foreach ($activeRows as $r) {
  $seq++;

  // itens
  $etaMin = (int)($r['eta_min'] ?? 0);
  $items = [];
  $stI = $pdo->prepare($itemsSQL);
  $stI->execute([(int)$r['id']]);
  $rowsI = $stI->fetchAll(PDO::FETCH_ASSOC);

  // fallback do ETA somando tempos dos produtos
  if ($etaMin <= 0) $etaMin = 0;

  foreach ($rowsI as $it) {
    $exList = [];
    $stE = $pdo->prepare($extrasSQL);
    $stE->execute([(int)$it['order_item_id']]);
    foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $ex) $exList[] = $ex['name'];

    if ($etaMin <= 0) $etaMin += max(0,(int)$it['prep_time_min'])*(int)$it['qty'];

    $items[] = [
      'qty'   => (int)$it['qty'],
      'name'  => $it['product_name'],
      'extras'=> $exList,
      'note'  => null
    ];
  }

  $active[] = [
    'oid'            => (int)$r['id'],
    'code'           => $r['code'],
    'seq'            => $seq,
    'table'          => $r['table_ref'] ?: null,
    'status'         => $r['status'],
    'observations'   => $r['observations'],
    'created_at'     => $r['created_at'],
    'last_change'    => $r['last_change'] ?: $r['created_at'],
    'prep_started_at'=> $r['prep_started_at'],     // <- novo
    'eta_min'        => $etaMin,                   // <- novo
    'items'          => $items
  ];
}

/** HISTÓRICO 24h **/
$sqlHist = "
  WITH last_change AS ($lastChangeSQL)
  SELECT o.id, o.code, o.created_at, lc.last_change
  FROM orders o
  LEFT JOIN last_change lc ON lc.order_id = o.id
  WHERE o.status = 'finalizado'
    AND o.created_at >= NOW() - INTERVAL 1 DAY
  $sinceSql
  ORDER BY lc.last_change DESC, o.id DESC
";
$stH = $pdo->prepare($sqlHist);
if ($since) $stH->execute([$since]); else $stH->execute();
$histRows = $stH->fetchAll(PDO::FETCH_ASSOC);

$history = [];
foreach ($histRows as $r) {
  $stI = $pdo->prepare($itemsSQL);
  $stI->execute([(int)$r['id']]);
  $names = [];
  foreach ($stI->fetchAll(PDO::FETCH_ASSOC) as $it) $names[] = ((int)$it['qty']).'× '.$it['product_name'];
  $history[] = [
    'oid'         => (int)$r['id'],
    'code'        => $r['code'],
    'created_at'  => $r['created_at'],
    'finalized_at'=> $r['last_change'] ?: $r['created_at'],
    'summary'     => implode(' • ', $names)
  ];
}

json_out([
  'server_time' => date('c'),
  'active'  => $active,
  'history' => $history
]);
