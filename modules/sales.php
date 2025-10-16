<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'ventas', 'compras', 'produccion']);

$orders = load_orders();
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Ventas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f9fb; color: #2c3e50; }
        header { background: #1b3a61; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 16px; }
        .container { padding: 32px; }
        h1 { margin-top: 0; }
        form { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        fieldset { border: 1px solid #dde3ec; padding: 16px; border-radius: 6px; margin-bottom: 24px; }
        legend { padding: 0 6px; font-weight: bold; color: #1b3a61; }
        label { display: block; margin-top: 12px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cfd9e5; border-radius: 4px; }
        textarea { resize: vertical; }
        button { padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #1b3a61; color: #fff; }
        .btn-secondary { background: #6c7a89; color: #fff; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .items-table th, .items-table td { border: 1px solid #e0e6ef; padding: 8px; text-align: left; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .status.compra-pendiente { background: #fdecea; color: #c0392b; }
        .status.compra-completado { background: #e8f5e9; color: #1e8449; }
        .status.produccion-pendiente { background: #fff4e6; color: #d35400; }
        .status.produccion-en-proceso { background: #ebf5fb; color: #1f618d; }
        .status.produccion-entregado { background: #e8f5e9; color: #1e8449; }
        table.orders { width: 100%; border-collapse: collapse; margin-top: 32px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        table.orders th, table.orders td { border: 1px solid #e0e6ef; padding: 10px; text-align: left; }
        table.orders th { background: #f0f4f8; }
        .actions { display: flex; gap: 8px; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; background: #e8f5e9; color: #1e8449; }
    </style>
</head>
<body>
<header>
    <div>
        <a href="/dashboard.php">Volver al panel</a>
        <strong>Módulo de Ventas</strong>
    </div>
    <div>
        <a href="/actions/logout.php">Cerrar sesión</a>
    </div>
</header>
<div class="container">
    <?php if ($success): ?>
        <div class="alert">La venta se registró correctamente y fue notificada a Compras y Producción.</div>
    <?php elseif ($error): ?>
        <div class="alert" style="background:#fdecea;color:#c0392b;">Hubo un problema al guardar la venta. Revisá los datos obligatorios.</div>
    <?php endif; ?>
    <h1>Registrar nueva venta</h1>
    <form action="/actions/save_sale.php" method="post">
        <fieldset>
            <legend>Datos del cliente</legend>
            <label>Nombre completo
                <input type="text" name="client_name" required>
            </label>
            <label>Correo electrónico
                <input type="email" name="client_email" required>
            </label>
            <label>Teléfono de contacto
                <input type="text" name="client_phone" required>
            </label>
            <label>Dirección de entrega
                <input type="text" name="delivery_address" required>
            </label>
            <label>Ciudad / Provincia
                <input type="text" name="delivery_city" required>
            </label>
            <label>Fecha comprometida de entrega
                <input type="date" name="delivery_date" required>
            </label>
        </fieldset>
        <fieldset>
            <legend>Detalles de la venta</legend>
            <label>Método de envío
                <input type="text" name="shipping_method" placeholder="Envío interno, courier, retiro, etc." required>
            </label>
            <label>Método de pago
                <input type="text" name="payment_method" required>
            </label>
            <label>Estado del pago
                <select name="payment_status" required>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagado">Pagado</option>
                </select>
            </label>
            <label>Monto total facturado (ARS)
                <input type="number" step="0.01" min="0" name="total_amount" required>
            </label>
            <label>Observaciones para producción y compras
                <textarea name="notes" rows="3" placeholder="Consideraciones especiales, empaques, urgencias..."></textarea>
            </label>
        </fieldset>
        <fieldset>
            <legend>Prendas solicitadas</legend>
            <table class="items-table" id="items-table">
                <thead>
                    <tr>
                        <th>Tipo de prenda</th>
                        <th>Tejido</th>
                        <th>Color</th>
                        <th>Talle</th>
                        <th>Cantidad</th>
                        <th>Impresiones</th>
                        <th>Archivo entregado</th>
                        <th>Formato</th>
                        <th>Personalización</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="items[0][type]" required></td>
                        <td><input type="text" name="items[0][fabric]" required></td>
                        <td><input type="text" name="items[0][color]" required></td>
                        <td><input type="text" name="items[0][size]" required></td>
                        <td><input type="number" min="1" name="items[0][quantity]" value="1" required></td>
                        <td><input type="number" min="0" name="items[0][print_count]" value="1" required></td>
                        <td>
                            <select name="items[0][artwork_delivered]">
                                <option value="si">Sí</option>
                                <option value="no">No</option>
                            </select>
                        </td>
                        <td><input type="text" name="items[0][artwork_format]" placeholder="AI, PNG, JPG..."></td>
                        <td><input type="text" name="items[0][personalization]" placeholder="Frente, espalda, nombres..."></td>
                        <td><button type="button" class="btn-secondary" onclick="removeItemRow(this)">Quitar</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn-secondary" onclick="addItemRow()">Agregar prenda</button>
        </fieldset>
        <button type="submit" class="btn-primary">Guardar venta</button>
    </form>

    <h2>Órdenes registradas</h2>
    <table class="orders">
        <thead>
        <tr>
            <th>Cliente</th>
            <th>Entrega</th>
            <th>Monto</th>
            <th>Pago</th>
            <th>Compra</th>
            <th>Producción</th>
            <th>Última actualización</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($order['client']['name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($order['client']['email']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($order['delivery']['date']); ?><br><small><?php echo htmlspecialchars($order['delivery']['address']); ?></small></td>
                <td><?php echo format_currency((float)$order['total_amount']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($order['payment']['status'])); ?> (<?php echo htmlspecialchars($order['payment']['method']); ?>)</td>
                <td>
                    <span class="status compra-<?php echo htmlspecialchars($order['purchase_status']); ?>">
                        <?php echo ucfirst($order['purchase_status']); ?>
                    </span>
                </td>
                <td>
                    <span class="status produccion-<?php echo htmlspecialchars($order['production_status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $order['production_status'])); ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($order['updated_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
let itemIndex = 1;
function addItemRow() {
    const tbody = document.querySelector('#items-table tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="items[${itemIndex}][type]" required></td>
        <td><input type="text" name="items[${itemIndex}][fabric]" required></td>
        <td><input type="text" name="items[${itemIndex}][color]" required></td>
        <td><input type="text" name="items[${itemIndex}][size]" required></td>
        <td><input type="number" min="1" name="items[${itemIndex}][quantity]" value="1" required></td>
        <td><input type="number" min="0" name="items[${itemIndex}][print_count]" value="1" required></td>
        <td>
            <select name="items[${itemIndex}][artwork_delivered]">
                <option value="si">Sí</option>
                <option value="no">No</option>
            </select>
        </td>
        <td><input type="text" name="items[${itemIndex}][artwork_format]" placeholder="AI, PNG, JPG..."></td>
        <td><input type="text" name="items[${itemIndex}][personalization]" placeholder="Frente, espalda, nombres..."></td>
        <td><button type="button" class="btn-secondary" onclick="removeItemRow(this)">Quitar</button></td>
    `;
    tbody.appendChild(row);
    itemIndex++;
}

function removeItemRow(button) {
    const row = button.closest('tr');
    const tbody = row.parentNode;
    if (tbody.children.length > 1) {
        tbody.removeChild(row);
    }
}
</script>
</body>
</html>
