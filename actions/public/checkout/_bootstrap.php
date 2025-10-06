<?php
declare(strict_types=1);

// Headers dev
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$publicDir = realpath(__DIR__ . '/..');      // /actions/public
require_once $publicDir . '/../includes/db.php'; // define $pdo (PDO)

/** Helpers */
function json_out($arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function read_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $d = json_decode($raw, true);
  if (!is_array($d)) json_out(['error' => 'INVALID_JSON'], 400);
  return $d;
}
function i($v): int { return max(0, (int)$v); }
function to_cents($v): int { return (int)round(((float)$v) * 100); }
function from_cents($c): float { return round(((int)$c) / 100, 2); }

/** Código do pedido tipo #361949 (único) */
function gen_order_code(PDO $pdo): string {
  do {
    $code = '#' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $st = $pdo->prepare("SELECT 1 FROM orders WHERE code = ? LIMIT 1");
    $st->execute([$code]);
    $exists = (bool)$st->fetchColumn();
  } while ($exists);
  return $code;
}
