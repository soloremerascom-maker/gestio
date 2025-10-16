<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'compras', 'ventas', 'produccion']);

$user = current_user();
$canManage = in_array($user['role'], ['admin', 'compras'], true);
$orders = load_orders();
$pendingOrders = array_filter($orders, fn($o) => ($o['purchase_status'] ?? 'pendiente') !== 'completado');
$completedOrders = array_filter($orders, fn($o) => ($o['purchase_status'] ?? 'pendiente') === 'completado');
$summary = aggregate_items($orders);
$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Compras</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #2c3e50; }
        header { background: #1b3a61; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 12px; }
        .container { padding: 32px; }
        h1 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; background: #fff; }
        th, td { border: 1px solid #dbe2ec; padding: 10px; text-align: left; }
        th { background: #f0f4f8; }
        .badge { display: inline-block; padding: 4px 8px; background: #1b3a61; color: #fff; border-radius: 12px; font-size: 12px; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .alert { background: #e8f5e9; color: #1e8449; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
        button, input[type="submit"] { padding: 10px 16px; border: none; border-radius: 4px; background: #1b3a61; color: #fff; cursor: pointer; }
        button:hover, input[type="submit"]:hover { background: #16304f; }
        input[type="number"] { width: 120px; padding: 8px; border: 1px solid #cfd9e5; border-radius: 4px; }
        .section { margin-top: 32px; }
    </style>
</head>
<body>
<header>
    <div>
        <a href="<?php echo htmlspecialchars(app_url('dashboard.php')); ?>">Panel</a>
        <strong>Módulo de Compras</strong>
    </div>
    <div>
        <a href="<?php echo htmlspecialchars(app_url('actions/logout.php')); ?>">Cerrar sesión</a>
    </div>
</header>
<div class="container">
    <?php if ($success): ?>
        <div class="alert">El estado de compra se actualizó correctamente.</div>
    <?php endif; ?>
    <h1>Órdenes pendientes de compra <span class="badge"><?php echo count($pendingOrders); ?></span></h1>
    <?php if (empty($pendingOrders)): ?>
        <p>No hay compras pendientes en este momento.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Cliente</th>
                <th>Entrega</th>
                <th>Detalle</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingOrders as $order): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($order['client']['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($order['client']['email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($order['delivery']['date']); ?><br><small><?php echo htmlspecialchars($order['delivery']['address']); ?></small></td>
                    <td>
                        <ul>
                            <?php foreach ($order['items'] as $item): ?>
                                <li><?php echo htmlspecialchars($item['quantity'] . ' x ' . $item['fabric'] . ' ' . $item['color'] . ' ' . $item['type'] . ' T' . $item['size']); ?> (impresiones: <?php echo (int)$item['print_count']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td><?php echo format_currency((float)$order['total_amount']); ?></td>
                    <td>
                        <?php if ($canManage): ?>
                            <form action="<?php echo htmlspecialchars(app_url('actions/update_purchase_status.php')); ?>" method="post" class="actions">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                <label>Costo (ARS)
                                    <input type="number" step="0.01" min="0" name="purchase_cost" value="<?php echo htmlspecialchars($order['purchase_cost'] ?? 0); ?>" required>
                                </label>
                                <input type="submit" value="Marcar como comprado">
                            </form>
                        <?php else: ?>
                            <span>En revisión por Compras</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($canManage): ?>
            <div class="section">
                <form action="<?php echo htmlspecialchars(app_url('actions/generate_purchase_pdf.php')); ?>" method="get" target="_blank">
                    <button type="submit">Descargar lista consolidada en PDF</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="section">
        <h2>Resumen consolidado para comprar</h2>
        <?php if (empty($summary)): ?>
            <p>Todo está comprado por ahora.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Tejido</th>
                    <th>Color</th>
                    <th>Talle</th>
                    <th>Cantidad total</th>
                    <th>Impresiones totales</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['type']); ?></td>
                        <td><?php echo htmlspecialchars($item['fabric']); ?></td>
                        <td><?php echo htmlspecialchars($item['color']); ?></td>
                        <td><?php echo htmlspecialchars($item['size']); ?></td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td><?php echo (int)$item['total_prints']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Órdenes ya compradas</h2>
        <?php if (empty($completedOrders)): ?>
            <p>Aún no hay registros de compras cerradas.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Entrega</th>
                    <th>Costo</th>
                    <th>Fecha de compra</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($completedOrders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['client']['name']); ?></td>
                        <td><?php echo htmlspecialchars($order['delivery']['date']); ?></td>
                        <td><?php echo format_currency((float)($order['purchase_cost'] ?? 0)); ?></td>
                        <td><?php echo htmlspecialchars($order['purchase_completed_at'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
