<?php
// actions/_guard.php
session_start();
if (empty($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'message'=>'Não autorizado']);
  exit;
}
