<?php
require_once __DIR__ . '/lib/helpers.php';
require_login();
$user = current_user();
$orders = load_orders();
$metrics = calculate_metrics($orders);

$roleNavigation = [
    'admin' => [
        ['href' => app_url('modules/admin.php'), 'label' => 'Administrador'],
        ['href' => app_url('modules/sales.php'), 'label' => 'Ventas'],
        ['href' => app_url('modules/purchases.php'), 'label' => 'Compras'],
        ['href' => app_url('modules/production.php'), 'label' => 'Producción'],
    ],
    'ventas' => [
        ['href' => app_url('modules/sales.php'), 'label' => 'Ventas'],
        ['href' => app_url('modules/purchases.php'), 'label' => 'Compras (consulta)'],
    ],
    'compras' => [
        ['href' => app_url('modules/purchases.php'), 'label' => 'Compras'],
        ['href' => app_url('modules/sales.php'), 'label' => 'Ventas (consulta)'],
    ],
    'produccion' => [
        ['href' => app_url('modules/production.php'), 'label' => 'Producción'],
        ['href' => app_url('modules/sales.php'), 'label' => 'Ventas (consulta)'],
    ],
];
$navigation = $roleNavigation[$user['role']] ?? [['href' => app_url('modules/sales.php'), 'label' => 'Ventas']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #eef2f5; color: #2c3e50; }
        header { background: #1b3a61; color: #fff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 20px; }
        header .user-info { font-size: 14px; }
        nav { background: #fff; padding: 12px 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        nav a { margin-right: 16px; color: #1b3a61; text-decoration: none; font-weight: 600; }
        nav a:hover { text-decoration: underline; }
        .container { padding: 32px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; color: #1b3a61; }
        .list { list-style: none; padding: 0; margin: 0; }
        .list li { padding: 6px 0; border-bottom: 1px solid #f0f2f5; }
        .list li:last-child { border-bottom: none; }
        .badge { display: inline-block; padding: 4px 8px; background: #1b3a61; color: #fff; border-radius: 12px; font-size: 12px; margin-left: 6px; }
        .logout { color: #fff; text-decoration: none; font-weight: bold; }
        .logout:hover { text-decoration: underline; }
    </style>
</head>
<body>
<header>
    <h1>Panel General</h1>
    <div class="user-info">
        Bienvenido, <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
        &nbsp;|&nbsp;
        <a class="logout" href="<?php echo htmlspecialchars(app_url('actions/logout.php')); ?>">Cerrar sesión</a>
    </div>
</header>
<nav>
    <?php foreach ($navigation as $link): ?>
        <a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
    <?php endforeach; ?>
</nav>
<div class="container">
    <div class="cards">
        <div class="card">
            <h3>Ingresos</h3>
            <p><?php echo format_currency($metrics['ingresos']); ?></p>
        </div>
        <div class="card">
            <h3>Gastos</h3>
            <p><?php echo format_currency($metrics['gastos']); ?></p>
        </div>
        <div class="card">
            <h3>Egresos pendientes</h3>
            <p><?php echo format_currency($metrics['egresos']); ?></p>
        </div>
        <div class="card">
            <h3>Totales</h3>
            <ul class="list">
                <li>Órdenes registradas <span class="badge"><?php echo $metrics['totales']['ordenes']; ?></span></li>
                <li>Pendientes de compra <span class="badge"><?php echo $metrics['totales']['pendientes_compra']; ?></span></li>
                <li>Pendientes de producción <span class="badge"><?php echo $metrics['totales']['pendientes_produccion']; ?></span></li>
            </ul>
        </div>
        <div class="card" style="grid-column: span 2;">
            <h3>Artículos más vendidos</h3>
            <?php if (!empty($metrics['top_items'])): ?>
                <ul class="list">
                    <?php foreach ($metrics['top_items'] as $item => $cantidad): ?>
                        <li><?php echo htmlspecialchars(ucfirst($item)); ?> <span class="badge"><?php echo $cantidad; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No hay datos suficientes todavía.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
