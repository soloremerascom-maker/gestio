<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'ventas']);

$client = [
    'name' => trim($_POST['client_name'] ?? ''),
    'email' => trim($_POST['client_email'] ?? ''),
    'phone' => trim($_POST['client_phone'] ?? ''),
];
$delivery = [
    'address' => trim($_POST['delivery_address'] ?? ''),
    'city' => trim($_POST['delivery_city'] ?? ''),
    'date' => $_POST['delivery_date'] ?? '',
    'method' => trim($_POST['shipping_method'] ?? ''),
];
$payment = [
    'method' => trim($_POST['payment_method'] ?? ''),
    'status' => $_POST['payment_status'] ?? 'pendiente',
];
$totalAmount = (float)($_POST['total_amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$itemsInput = $_POST['items'] ?? [];

$items = [];
foreach ($itemsInput as $item) {
    $type = trim($item['type'] ?? '');
    if ($type === '') {
        continue;
    }
    $items[] = [
        'type' => $type,
        'fabric' => trim($item['fabric'] ?? ''),
        'color' => trim($item['color'] ?? ''),
        'size' => trim($item['size'] ?? ''),
        'quantity' => (int)($item['quantity'] ?? 0),
        'print_count' => (int)($item['print_count'] ?? 0),
        'artwork_delivered' => $item['artwork_delivered'] ?? 'no',
        'artwork_format' => trim($item['artwork_format'] ?? ''),
        'personalization' => trim($item['personalization'] ?? ''),
    ];
}

if ($client['name'] === '' || $client['email'] === '' || empty($items)) {
    redirect_to('modules/sales.php?error=1');
}

$orders = load_orders();
$now = date('Y-m-d H:i:s');
$orderId = uniqid('order_');

$order = [
    'id' => $orderId,
    'created_at' => $now,
    'updated_at' => $now,
    'client' => $client,
    'delivery' => $delivery,
    'payment' => $payment,
    'total_amount' => $totalAmount,
    'notes' => $notes,
    'items' => $items,
    'purchase_status' => 'pendiente',
    'production_status' => 'pendiente',
    'purchase_cost' => 0,
    'history' => [
        ['at' => $now, 'actor' => current_user()['username'], 'action' => 'Registro de venta'],
    ],
];

array_unshift($orders, $order);
save_orders($orders);

$bodyLines = [
    'Se registró una nueva orden de remeras personalizadas.',
    'Cliente: ' . $client['name'],
    'Contacto: ' . $client['email'] . ' / ' . $client['phone'],
    'Entrega: ' . $delivery['date'] . ' - ' . $delivery['address'] . ', ' . $delivery['city'] . ' (' . $delivery['method'] . ')',
    'Pago: ' . ucfirst($payment['status']) . ' vía ' . $payment['method'],
    'Monto total: $' . number_format($totalAmount, 2, ',', '.'),
    'Observaciones: ' . ($notes !== '' ? $notes : 'Sin observaciones'),
    'Detalle de prendas:',
];
foreach ($items as $item) {
    $bodyLines[] = sprintf(
        '- %d x %s %s %s talle %s | impresiones: %d | archivo: %s (%s) | personalización: %s',
        $item['quantity'],
        $item['fabric'],
        $item['color'],
        $item['type'],
        $item['size'],
        $item['print_count'],
        strtoupper($item['artwork_delivered']),
        $item['artwork_format'] !== '' ? $item['artwork_format'] : 'N/D',
        $item['personalization'] !== '' ? $item['personalization'] : 'General'
    );
}
$body = implode("\n", $bodyLines);

foreach (SYSTEM_EMAILS as $email) {
    $subject = sprintf('%s - hay una nueva venta %s', $client['name'], current_user()['username']);
    @mail($email, $subject, $body);
}

$logEntry = '[' . $now . "] Notificación enviada a " . implode(', ', SYSTEM_EMAILS) . " sobre la orden " . $orderId . "\n";
file_put_contents(EMAIL_LOG_FILE, $logEntry, FILE_APPEND);

redirect_to('modules/sales.php?success=1');
