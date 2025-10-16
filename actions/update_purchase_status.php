<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'compras']);

$orderId = $_POST['order_id'] ?? '';
$purchaseCost = (float)($_POST['purchase_cost'] ?? 0);

if ($orderId === '') {
    header('Location: /modules/purchases.php?error=1');
    exit;
}

$orders = load_orders();
$index = find_order_index($orders, $orderId);
if ($index === null) {
    header('Location: /modules/purchases.php?error=1');
    exit;
}

$now = date('Y-m-d H:i:s');
$orders[$index]['purchase_status'] = 'completado';
$orders[$index]['purchase_cost'] = $purchaseCost;
$orders[$index]['purchase_completed_at'] = $now;
$orders[$index]['updated_at'] = $now;
$orders[$index]['history'][] = [
    'at' => $now,
    'actor' => current_user()['username'],
    'action' => 'Compra finalizada',
];

save_orders($orders);

header('Location: /modules/purchases.php?success=1');
exit;
