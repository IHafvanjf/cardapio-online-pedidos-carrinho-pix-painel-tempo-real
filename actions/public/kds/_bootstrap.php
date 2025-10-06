<?php
declare(strict_types=1);

// Headers (dev)
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$publicDir = realpath(__DIR__ . '/..');            // /actions/public
require_once dirname(__DIR__, 3) . '/includes/db.php';
// de kds/ → public (..), → actions (../..), → cardapio (../../..) + /includes/db.php

// Helpers
function json_out($arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function i($v): int { return max(0, (int)$v); }
function read_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $d = json_decode($raw, true);
  if (!is_array($d)) json_out(['error'=>'INVALID_JSON'], 400);
  return $d;
}
