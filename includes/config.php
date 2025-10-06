<?php
// Config central do app
$CONFIG = [
  'db' => [
    'host'    => '127.0.0.1',
    'port'    => '3306',
    'name'    => 'u953537988_cardapio',
    'user'    => 'u953537988_cardapio',
    'pass'    => '13579012Victor)',
    'charset' => 'utf8mb4',
  ],
  'security' => [
    'csrf_key' => '123',
  ],
  // Credenciais Mercado Pago
  'mp' => [
    // Use TEST-.... para sandbox ou APP_USR-.... em produção
    'access_token' => 'APP_USR-3633812690502257-082215-6c4a237c92c5cd62d8f3e46881a6e127-1096120387',
    'public_key'   => 'APP_USR-7001b759-ba20-4190-b64a-5da0b7098c83',
    'env'          => 'test', // 'prod' se estiver usando APP_USR
  ],
];

// Define constantes para quem usa constante
if (!defined('MP_ACCESS_TOKEN')) define('MP_ACCESS_TOKEN', $CONFIG['mp']['access_token']);
if (!defined('MP_PUBLIC_KEY'))   define('MP_PUBLIC_KEY',   $CONFIG['mp']['public_key']);
if (!defined('MP_ENV'))          define('MP_ENV',          $CONFIG['mp']['env']);

date_default_timezone_set('America/Sao_Paulo');

return $CONFIG;
