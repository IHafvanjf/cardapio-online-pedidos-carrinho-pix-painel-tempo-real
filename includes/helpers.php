<?php
function json_ok($data = [], $message = 'ok') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_error($message = 'Erro', $http = 400, $data = []) {
  http_response_code($http);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}
function only_post() { if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Método inválido', 405); }
function only_get()  { if ($_SERVER['REQUEST_METHOD'] !== 'GET')  json_error('Método inválido', 405); }

function sanitize_price($str) { // "R$ 22,90" -> 22.90
  $str = preg_replace('/[^0-9,.\-]/', '', $str);
  if (strpos($str, ',') !== false && strpos($str, '.') === false) $str = str_replace(',', '.', $str);
  return number_format((float)$str, 2, '.', '');
}

function csrf_token() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $t = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_error('CSRF inválido', 403);
}
