<?php
declare(strict_types=1);

/**
 * Gera um pagamento PIX no Mercado Pago para um pedido existente.
 * POST JSON: { "order_id": 123 }  ou  { "code": "#361949" }
 * Saída (200): { order_id, code, pix: { copy_paste, qr_code_base64 }, expires_at? }
 */

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-BH-PayPix: v3'); // ajuda a confirmar que este arquivo está em uso

/* ===== Includes (db + config) ===== */
$here = __DIR__;
$candidates = [
  dirname($here, 3) . '/includes/db.php',  // .../cardapio/includes/db.php
  dirname($here, 2) . '/includes/db.php',  // .../actions/includes/db.php (se usar outra estrutura)
];
$dbfile = null;
foreach ($candidates as $p) { if (is_file($p)) { $dbfile = $p; break; } }
if (!$dbfile) { http_response_code(500); echo json_encode(['error'=>'DB_FILE_NOT_FOUND']); exit; }
require_once $dbfile;

// (db.php já requer config.php e cria $pdo)
$configPath = dirname($dbfile) . '/config.php';
if (is_file($configPath)) require_once $configPath;

/* ===== Helpers ===== */
function out(array $arr, int $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function i($v){ return max(0,(int)$v); }
function read_json(): array { $raw=file_get_contents('php://input')?:''; $d=json_decode($raw,true); return is_array($d)?$d:[]; }
function log_mp(string $msg){ @file_put_contents(__DIR__.'/mp_pix.log', date('c').' '.$msg.PHP_EOL, FILE_APPEND); }

/* ===== Guardas ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error'=>'METHOD_NOT_ALLOWED'], 405);
if (!isset($pdo)) out(['error'=>'PDO_NOT_AVAILABLE'], 500);
if (!defined('MP_ACCESS_TOKEN') || !MP_ACCESS_TOKEN) out(['error'=>'NO_ACCESS_TOKEN'], 500);

/* ===== Entrada ===== */
$body    = read_json();
$orderId = i($body['order_id'] ?? 0);
$codeIn  = (string)($body['code'] ?? '');

if ($orderId <= 0 && $codeIn === '') out(['error'=>'MISSING_ORDER_ID_OR_CODE'], 400);

/* ===== Carrega pedido ===== */
if ($orderId > 0) {
  $st = $pdo->prepare("SELECT id, code, total, status, customer_name FROM orders WHERE id=? LIMIT 1");
  $st->execute([$orderId]);
} else {
  $st = $pdo->prepare("SELECT id, code, total, status, customer_name FROM orders WHERE code=? LIMIT 1");
  $st->execute([$codeIn]);
}
$o = $st->fetch(PDO::FETCH_ASSOC);
if (!$o) out(['error'=>'ORDER_NOT_FOUND'], 404);

$amount    = (float)$o['total'];
$code      = (string)$o['code'];
$firstName = trim(explode(' ', (string)$o['customer_name'])[0] ?? '') ?: 'Cliente';

if ($amount <= 0) out(['error'=>'INVALID_AMOUNT', 'total'=>$amount], 422);

/* ===== Ambiente / payer ===== */
$env = (defined('MP_ENV') ? strtolower((string)MP_ENV) : 'test'); // 'test' | 'prod'

// Em sandbox use sempre CPF de teste 19119119100; em prod, pode usar '00000000191' se não tiver CPF real
$cpf   = ($env === 'prod') ? '00000000191' : '19119119100';
$email = ($env === 'prod') ? ('cliente+'.substr($code,1).'@example.com') : ('cliente.teste+'.substr($code,1).'@example.com');

/* ===== Payload MP ===== */
$idemp = 'pix_' . bin2hex(random_bytes(8));

// Por padrão NÃO enviamos date_of_expiration (para evitar 400). 
// Se quiser ativar, defina MP_USE_EXPIRATION=true no config e calculamos no formato correto (Y-m-dTH:i:s.vP).
$useExp = defined('MP_USE_EXPIRATION') ? (bool)MP_USE_EXPIRATION : false;
$expIso = null;
if ($useExp) {
  $tz    = new DateTimeZone('America/Sao_Paulo');
  $expIso = (new DateTime('now', $tz))->add(new DateInterval('PT30M'))->format('Y-m-d\TH:i:s.vP'); // ex: 2025-08-24T21:00:00.000-03:00
}

$payload = [
  "transaction_amount" => $amount,
  "description"        => "Pedido $code",
  "payment_method_id"  => "pix",
  "external_reference" => $code,
  "payer" => [
    "email"          => $email,
    "first_name"     => $firstName,
    "identification" => [ "type" => "CPF", "number" => $cpf ]
  ],
  "metadata" => [ "order_id" => (int)$o['id'], "code" => $code ]
];
// adiciona expiração somente se habilitada
if ($expIso) { $payload["date_of_expiration"] = $expIso; }

/* ===== Chamada MP ===== */
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  CURLOPT_HTTPHEADER     => [
    "Content-Type: application/json",
    "Authorization: Bearer " . MP_ACCESS_TOKEN,
    "X-Idempotency-Key: $idemp"
  ],
  CURLOPT_TIMEOUT        => 30,
]);
$res  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

log_mp("create pix http=$http idemp=$idemp env=$env code=$code amount=$amount cerr=".($cerr?:'-')." payload=".json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)." res=".$res);

$data = json_decode($res, true);

/* ===== Erros MP ===== */
if ($http !== 201 || !isset($data['id'])) {
  out([
    'error'   => 'MP_ERROR',
    'http'    => $http,
    'message' => $data['message'] ?? ($data['error'] ?? 'unknown'),
    'cause'   => $data['cause'] ?? null
  ], 502);
}

/* ===== Retorno PIX ===== */
$qr    = $data['point_of_interaction']['transaction_data']['qr_code']        ?? null;
$qrB64 = $data['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
$expMP = $data['date_of_expiration'] ?? ($expIso ?: null);

if (!$qr) out(['error'=>'PIX_QR_NOT_RETURNED', 'raw'=>$data], 502);

out([
  'order_id'   => (int)$o['id'],
  'code'       => $o['code'],
  'pix'        => ['copy_paste' => $qr, 'qr_code_base64' => $qrB64],
  'expires_at' => $expMP
]);
