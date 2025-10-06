<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Exemplo simples:
   - Em produção, troque por login real com tabela users/admins.
*/
function require_admin() {
  if (empty($_SESSION['admin_logged'])) {
    http_response_code(401);
    echo 'Não autorizado.';
    exit;
  }
}
