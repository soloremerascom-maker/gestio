<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'produccion', 'ventas', 'compras']);

$user = current_user();
$canManage = in_array($user['role'], ['admin', 'produccion'], true);
$orders = load_orders();
$pending = array_filter($orders, fn($o) => ($o['production_status'] ?? 'pendiente') !== 'entregado');
$completed = array_filter($orders, fn($o) => ($o['production_status'] ?? 'pendiente') === 'entregado');
$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Producción</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f8fa; color: #2c3e50; }
        header { background: #1b3a61; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 12px; }
        .container { padding: 32px; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 16px; }
        th, td { border: 1px solid #dbe2ec; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f0f4f8; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .status.pendiente { background: #fff4e6; color: #d35400; }
        .status.en_proceso { background: #ebf5fb; color: #1f618d; }
        .status.entregado { background: #e8f5e9; color: #1e8449; }
        form { display: inline-flex; gap: 8px; align-items: center; }
        select, button { padding: 8px 12px; border-radius: 4px; border: 1px solid #cfd9e5; }
        button { background: #1b3a61; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #16304f; }
        .alert { background: #e8f5e9; color: #1e8449; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
    </style>
</head>
<body>
<header>
    <div>
        <a href="/dashboard.php">Panel</a>
        <strong>Módulo de Producción</strong>
    </div>
    <div>
        <a href="/actions/logout.php">Cerrar sesión</a>
    </div>
</header>
<div class="container">
    <?php if ($success): ?>
        <div class="alert">Estado de producción actualizado.</div>
    <?php endif; ?>
    <h1>Órdenes en producción</h1>
    <?php if (empty($pending)): ?>
        <p>No hay órdenes pendientes.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Cliente</th>
                <th>Entrega</th>
                <th>Compra</th>
                <th>Detalle</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $order): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($order['client']['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($order['client']['email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($order['delivery']['date']); ?><br><small><?php echo htmlspecialchars($order['delivery']['address']); ?></small></td>
                    <td><?php echo ucfirst($order['purchase_status']); ?><?php if (($order['purchase_status'] ?? '') === 'completado'): ?><br><small>Comprado el <?php echo htmlspecialchars($order['purchase_completed_at'] ?? ''); ?></small><?php endif; ?></td>
                    <td>
                        <ul>
                            <?php foreach ($order['items'] as $item): ?>
                                <li><?php echo htmlspecialchars($item['quantity'] . ' x ' . $item['type'] . ' ' . $item['color'] . ' T' . $item['size']); ?> - Archivo: <?php echo strtoupper($item['artwork_delivered']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!empty($order['notes'])): ?>
                            <small><strong>Notas:</strong> <?php echo htmlspecialchars($order['notes']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status <?php echo htmlspecialchars($order['production_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['production_status'])); ?></span>
                        <?php if ($canManage): ?>
                            <form action="/actions/update_production_status.php" method="post">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                <select name="production_status">
                                    <option value="pendiente" <?php echo $order['production_status'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="en_proceso" <?php echo $order['production_status'] === 'en_proceso' ? 'selected' : ''; ?>>En proceso</option>
                                    <option value="entregado" <?php echo $order['production_status'] === 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                                </select>
                                <button type="submit">Actualizar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Órdenes completadas</h2>
    <?php if (empty($completed)): ?>
        <p>Todavía no hay entregas finalizadas.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Cliente</th>
                <th>Fecha entrega</th>
                <th>Fecha cierre</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($completed as $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['client']['name']); ?></td>
                    <td><?php echo htmlspecialchars($order['delivery']['date']); ?></td>
                    <td><?php echo htmlspecialchars($order['production_completed_at'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
