<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin']);

$orders = load_orders();
$metrics = calculate_metrics($orders);
$users = load_users();
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo Administrador</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f7fa; color: #34495e; }
        header { background: #1b3a61; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 16px; }
        .container { padding: 32px; }
        h1 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; }
        .card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
        .card h2 { margin-top: 0; color: #1b3a61; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 10px; border-bottom: 1px solid #e1e7f0; text-align: left; }
        th { background: #f0f4f8; }
        form { margin-top: 16px; }
        label { display: block; margin-top: 12px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cfd9e5; border-radius: 4px; }
        button { margin-top: 16px; padding: 12px 20px; border: none; border-radius: 4px; background: #1b3a61; color: #fff; cursor: pointer; }
        button:hover { background: #16304f; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
        .alert.success { background: #e8f5e9; color: #2d7a32; }
        .alert.error { background: #fdecea; color: #c0392b; }
        .metrics { list-style: none; padding: 0; margin: 0; }
        .metrics li { padding: 6px 0; border-bottom: 1px solid #f0f2f5; }
        .metrics li:last-child { border-bottom: none; }
        .nav-links a { margin-right: 12px; }
    </style>
</head>
<body>
<header>
    <div>
        <strong>Administrador</strong>
        <span class="nav-links">
            <a href="<?php echo htmlspecialchars(app_url('dashboard.php')); ?>">Panel</a>
            <a href="<?php echo htmlspecialchars(app_url('modules/sales.php')); ?>">Ventas</a>
            <a href="<?php echo htmlspecialchars(app_url('modules/purchases.php')); ?>">Compras</a>
            <a href="<?php echo htmlspecialchars(app_url('modules/production.php')); ?>">Producción</a>
        </span>
    </div>
    <div>
        <a href="<?php echo htmlspecialchars(app_url('actions/logout.php')); ?>">Cerrar sesión</a>
    </div>
</header>
<div class="container">
    <?php if ($success): ?>
        <div class="alert success">Cambios guardados correctamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error">No se pudo realizar la acción solicitada.</div>
    <?php endif; ?>
    <div class="grid">
        <div class="card">
            <h2>Indicadores clave</h2>
            <ul class="metrics">
                <li>Ingresos totales: <?php echo format_currency($metrics['ingresos']); ?></li>
                <li>Gastos realizados: <?php echo format_currency($metrics['gastos']); ?></li>
                <li>Egresos pendientes: <?php echo format_currency($metrics['egresos']); ?></li>
                <li>Órdenes registradas: <?php echo $metrics['totales']['ordenes']; ?></li>
                <li>Órdenes sin comprar: <?php echo $metrics['totales']['pendientes_compra']; ?></li>
                <li>Órdenes sin entregar: <?php echo $metrics['totales']['pendientes_produccion']; ?></li>
            </ul>
            <h3>Artículos más vendidos</h3>
            <ul class="metrics">
                <?php if (empty($metrics['top_items'])): ?>
                    <li>Sin datos</li>
                <?php else: ?>
                    <?php foreach ($metrics['top_items'] as $nombre => $cantidad): ?>
                        <li><?php echo htmlspecialchars(ucfirst($nombre)); ?>: <?php echo $cantidad; ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card">
            <h2>Crear nuevo usuario</h2>
            <form action="<?php echo htmlspecialchars(app_url('actions/create_user.php')); ?>" method="post">
                <label for="name">Nombre completo</label>
                <input type="text" id="name" name="name" required>

                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Contraseña inicial</label>
                <input type="password" id="password" name="password" required>

                <label for="role">Rol</label>
                <select id="role" name="role" required>
                    <option value="ventas">Ventas</option>
                    <option value="compras">Compras</option>
                    <option value="produccion">Producción</option>
                    <option value="admin">Administrador</option>
                </select>

                <button type="submit">Crear usuario</button>
            </form>
        </div>
        <div class="card" style="grid-column: span 2;">
            <h2>Usuarios del sistema</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Actualizar contraseña</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['role']); ?></td>
                            <td>
                                <form action="<?php echo htmlspecialchars(app_url('actions/update_user.php')); ?>" method="post" style="display:flex; gap:8px; align-items:center;">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['username']); ?>">
                                    <input type="password" name="new_password" placeholder="Nueva contraseña" required>
                                    <button type="submit">Actualizar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
