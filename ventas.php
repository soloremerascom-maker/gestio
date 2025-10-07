<?php
require_once __DIR__ . '/includes/data.php';

ensure_role(['ventas', 'admin']);

$sales = load_sales();
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim($_POST['client_name'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $deliveryDate = trim($_POST['delivery_date'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentStatus = trim($_POST['payment_status'] ?? 'Pendiente');
    $totalPrice = (float)($_POST['total_price'] ?? 0);
    $advancePayment = (float)($_POST['advance_payment'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $productionStatus = trim($_POST['production_status'] ?? 'pendiente');

    $garmentTypes = $_POST['item_garment_type'] ?? [];
    $materials = $_POST['item_material'] ?? [];
    $colors = $_POST['item_color'] ?? [];
    $sizes = $_POST['item_size'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $printCounts = $_POST['item_prints'] ?? [];
    $fileTypes = $_POST['item_file_type'] ?? [];
    $fileSentFlags = $_POST['item_file_sent'] ?? [];

    $items = [];
    for ($i = 0; $i < count($garmentTypes); $i++) {
        if (trim($garmentTypes[$i]) === '') {
            continue;
        }
        $items[] = [
            'garment_type' => trim($garmentTypes[$i]),
            'material' => trim($materials[$i] ?? ''),
            'color' => trim($colors[$i] ?? ''),
            'size' => trim($sizes[$i] ?? ''),
            'quantity' => (int)($quantities[$i] ?? 0),
            'print_count' => (int)($printCounts[$i] ?? 0),
            'file_type' => trim($fileTypes[$i] ?? ''),
            'file_sent' => (($fileSentFlags[$i] ?? '') === 'si'),
            'purchase' => [
                'status' => 'pending',
                'cost' => 0,
                'updated_at' => null,
                'updated_by' => null,
            ],
        ];
    }

    if (empty($clientName) || empty($items)) {
        $error = 'Debe completar el nombre del cliente y al menos un artículo.';
    } else {
        $sale = [
            'client' => [
                'name' => $clientName,
                'email' => $clientEmail,
                'phone' => $clientPhone,
            ],
            'shipping' => [
                'address' => $shippingAddress,
                'delivery_date' => $deliveryDate,
            ],
            'items' => $items,
            'payment' => [
                'method' => $paymentMethod,
                'status' => $paymentStatus,
            ],
            'financial' => [
                'total_price' => $totalPrice,
                'advance_payment' => $advancePayment,
                'total_expense' => 0,
            ],
            'status' => [
                'purchase' => 'pending',
                'production' => $productionStatus !== '' ? $productionStatus : 'pendiente',
            ],
            'notes' => $notes,
            'created_at' => date('c'),
            'created_by' => $_SESSION['user']['username'],
        ];

        $sale = add_sale($sale);
        $sales = load_sales();

        // Simulación de envío de mails
        $subject = $sale['client']['name'] . ' - hay una nueva venta "' . $sale['created_by'] . '"';
        $body = "Se registró una nueva venta para " . $sale['client']['name'] . "\n" .
            "Total: $" . number_format($sale['financial']['total_price'], 2, ',', '.') . "\n" .
            "Entrega: " . ($sale['shipping']['delivery_date'] ?: 'A coordinar') . "\n" .
            "Responsable: " . $sale['created_by'] . "\n";

        $emails = [
            'compras@empresa.com',
            'produccion@empresa.com',
        ];
        foreach ($emails as $mail) {
            log_email($mail, $subject, $body);
        }

        $success = 'Venta cargada correctamente. Se notificó a Compras y Producción.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Ventas</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script defer src="assets/app.js"></script>
</head>
<body>
<header class="main-header">
    <h1>Módulo Ventas</h1>
    <nav>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="admin.php">Administración</a>
        <?php endif; ?>
        <a href="compras.php">Compras</a>
        <a href="logout.php" class="logout">Cerrar sesión</a>
    </nav>
</header>
<main class="container">
    <section class="card">
        <h2>Registrar nueva venta</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error; ?></div><?php endif; ?>
        <form method="post" id="sale-form">
            <div class="grid-2">
                <div>
                    <label for="client_name">Cliente</label>
                    <input type="text" name="client_name" id="client_name" required>
                </div>
                <div>
                    <label for="client_email">Email del cliente</label>
                    <input type="email" name="client_email" id="client_email">
                </div>
                <div>
                    <label for="client_phone">Teléfono</label>
                    <input type="text" name="client_phone" id="client_phone">
                </div>
                <div>
                    <label for="shipping_address">Dirección de entrega</label>
                    <input type="text" name="shipping_address" id="shipping_address">
                </div>
                <div>
                    <label for="delivery_date">Fecha compromiso</label>
                    <input type="date" name="delivery_date" id="delivery_date">
                </div>
                <div>
                    <label for="payment_method">Forma de pago</label>
                    <input type="text" name="payment_method" id="payment_method" placeholder="Efectivo, transferencia, etc">
                </div>
                <div>
                    <label for="payment_status">Estado del pago</label>
                    <select name="payment_status" id="payment_status">
                        <option>Pendiente</option>
                        <option>Seña recibida</option>
                        <option>Pagado</option>
                    </select>
                </div>
                <div>
                    <label for="total_price">Total presupuestado ($)</label>
                    <input type="number" step="0.01" name="total_price" id="total_price">
                </div>
                <div>
                    <label for="advance_payment">Seña cobrada ($)</label>
                    <input type="number" step="0.01" name="advance_payment" id="advance_payment">
                </div>
                <div>
                    <label for="production_status">Estado producción</label>
                    <select name="production_status" id="production_status">
                        <option value="pendiente">Pendiente</option>
                        <option value="en progreso">En progreso</option>
                        <option value="completado">Completado</option>
                    </select>
                </div>
            </div>

            <h3>Prendas solicitadas</h3>
            <div id="items-container"></div>
            <button type="button" class="secondary" id="add-item">Agregar prenda</button>

            <label for="notes">Observaciones</label>
            <textarea name="notes" id="notes" rows="4" placeholder="Detalles relevantes para producción y compras"></textarea>

            <button type="submit">Guardar venta</button>
        </form>
    </section>

    <section class="card">
        <h2>Ventas recientes</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Prendas</th>
                    <th>Estado compras</th>
                    <th>Estado producción</th>
                    <th>Entregar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="6">No hay ventas registradas.</td></tr>
                <?php else: ?>
                    <?php foreach (array_reverse($sales) as $sale): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sale['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <strong><?= htmlspecialchars($sale['client']['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <?= htmlspecialchars($sale['client']['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                                <?= htmlspecialchars($sale['client']['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <ul>
                                    <?php foreach ($sale['items'] as $item): ?>
                                        <li>
                                            <?= htmlspecialchars($item['quantity'] . 'x ' . $item['garment_type'] . ' ' . $item['material'] . ' ' . $item['color'] . ' T' . $item['size'], ENT_QUOTES, 'UTF-8'); ?><br>
                                            Impresiones: <?= (int)$item['print_count']; ?> | Archivo: <?= htmlspecialchars($item['file_type'], ENT_QUOTES, 'UTF-8'); ?> (<?= $item['file_sent'] ? 'enviado' : 'pendiente'; ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td><?= htmlspecialchars($sale['status']['purchase'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['status']['production'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['shipping']['delivery_date'] ?: 'A coordinar', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
