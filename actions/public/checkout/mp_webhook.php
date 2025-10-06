<?php
declare(strict_types=1);

// Respostas rápidas p/ o MP
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$here = __DIR__;
$candidates = [
  dirname($here, 3) . '/includes/db.php',
  dirname($here, 2) . '/includes/db.php',
];
$dbfile = null; foreach ($candidates as $p) if (is_file($p)) { $dbfile=$p; break; }
if (!$dbfile) { http_response_code(500); echo json_encode(['error'=>'DB_FILE_NOT_FOUND']); exit; }
require_once $dbfile;
$configPath = dirname($dbfile).'/config.php';
if (is_file($configPath)) require_once $configPath;

function log_wh($msg){ @file_put_contents(__DIR__.'/mp_webhook.log', date('c').' '.$msg.PHP_EOL, FILE_APPEND); }
function ok($arr=['ok'=>true]){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

// Lê tanto o formato novo (JSON) quanto o legado (?topic=payment&id=...)
$raw = file_get_contents('php://input') ?: '';
$event = json_decode($raw, true) ?: [];
$type = $event['type'] ?? $_GET['topic'] ?? '';
$paymentId = 0;

if (($type === 'payment') && isset($event['data']['id'])) {
  $paymentId = (int)$event['data']['id'];
} elseif (($type === 'payment') && isset($_GET['id'])) {
  $paymentId = (int)$_GET['id'];
}

// Se não for evento de payment, ignore educadamente (200)
if ($type !== 'payment' || $paymentId <= 0) { log_wh("IGNORED raw=".substr($raw,0,500)); ok(['ignored'=>true]); }

// Busca o pagamento no MP
if (!defined('MP_ACCESS_TOKEN') || !MP_ACCESS_TOKEN) { http_response_code(500); echo json_encode(['error'=>'NO_ACCESS_TOKEN']); exit; }

$ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ["Authorization: Bearer ".MP_ACCESS_TOKEN],
  CURLOPT_TIMEOUT => 20,
]);
$res  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($http !== 200) { log_wh("MP_GET_FAIL id=$paymentId http=$http cerr=$cerr res=$res"); ok(['mp_error'=>true,'http'=>$http]); }

$pay = json_decode($res, true) ?: [];
$extRef = $pay['external_reference'] ?? null;   // deve ser o code do pedido (#123456)
$mpStatus = strtolower((string)($pay['status'] ?? ''));
$mpStatusDetail = strtolower((string)($pay['status_detail'] ?? ''));

log_wh("PAY id=$paymentId status=$mpStatus detail=$mpStatusDetail ext=$extRef");

// Sem reference não dá pra casar com o pedido
if (!$extRef) ok(['no_reference'=>true]);

// Carrega pedido por code
$st = $pdo->prepare("SELECT id, status FROM orders WHERE code=? LIMIT 1");
$st->execute([$extRef]);
$ord = $st->fetch(PDO::FETCH_ASSOC);
if (!$ord) { log_wh("ORDER_NOT_FOUND code=$extRef"); ok(['order_not_found'=>true]); }

$orderId = (int)$ord['id'];
$from = $ord['status'];

// Mapeia status do MP -> status interno
$to = $from;
switch ($mpStatus) {
  case 'approved':
    $to = 'preparando';    // libera para a cozinha
    break;
  case 'rejected':
  case 'cancelled':
  case 'refunded':
  case 'charged_back':
    $to = 'cancelado';
    break;
  default:
    // pending / in_process / in_mediation: mantém o atual (aguardando)
    $to = $from;
}

if ($to !== $from) {
  try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$to, $orderId]);
    $pdo->prepare("INSERT INTO order_logs (order_id, from_status, to_status, changed_at) VALUES (?,?,?,NOW())")
        ->execute([$orderId, $from, $to]);
    $pdo->commit();
    log_wh("ORDER_UPDATED id=$orderId $from->$to");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_wh("DB_ERR id=$orderId err=".$e->getMessage());
  }
}

ok(['ok'=>true,'order_id'=>$orderId,'from'=>$from,'to'=>$to]);
