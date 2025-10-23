<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'produccion']);

$orderId = $_POST['order_id'] ?? '';
$status = $_POST['production_status'] ?? 'pendiente';

if ($orderId === '') {
    redirect_to('modules/production.php?error=1');
}

$allowed = ['pendiente', 'en_proceso', 'entregado'];
if (!in_array($status, $allowed, true)) {
    $status = 'pendiente';
}

$orders = load_orders();
$index = find_order_index($orders, $orderId);
if ($index === null) {
    redirect_to('modules/production.php?error=1');
}

$now = date('Y-m-d H:i:s');
$orders[$index]['production_status'] = $status;
$orders[$index]['updated_at'] = $now;
if ($status === 'entregado') {
    $orders[$index]['production_completed_at'] = $now;
}
$orders[$index]['history'][] = [
    'at' => $now,
    'actor' => current_user()['username'],
    'action' => 'Actualizó producción a ' . $status,
];

save_orders($orders);
redirect_to('modules/production.php?success=1');
