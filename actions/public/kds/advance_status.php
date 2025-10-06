<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';

$body    = read_json();
$orderId = i($body['order_id'] ?? 0);
$to      = (string)($body['to_status'] ?? '');

$allowed = ['preparando','finalizado'];
if ($orderId <= 0 || !in_array($to, $allowed, true)) {
  json_out(['error'=>'INVALID_INPUT'], 400);
}

$st = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? LIMIT 1");
$st->execute([$orderId]);
$o = $st->fetch(PDO::FETCH_ASSOC);
if (!$o) json_out(['error'=>'ORDER_NOT_FOUND'], 404);

$from = $o['status'];
$ok = ($from === 'aguardando' && $to === 'preparando')
   || ($from === 'preparando' && $to === 'finalizado');
if (!$ok) json_out(['error'=>'INVALID_TRANSITION','from'=>$from,'to'=>$to], 422);

try {
  $pdo->beginTransaction();

  if ($from === 'aguardando' && $to === 'preparando') {
    // marca início de preparo na primeira transição
    $pdo->prepare("UPDATE orders SET status=?, prep_started_at=IFNULL(prep_started_at, NOW()) WHERE id=?")
        ->execute([$to, $orderId]);
  } else {
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$to, $orderId]);
  }

  $pdo->prepare("INSERT INTO order_logs (order_id, from_status, to_status, changed_at)
                 VALUES (?, ?, ?, NOW())")->execute([$orderId, $from, $to]);

  $pdo->commit();
  json_out(['success'=>true, 'order_id'=>$orderId, 'from'=>$from, 'to'=>$to]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['error'=>'SERVER_ERROR','message'=>$e->getMessage()], 500);
}
