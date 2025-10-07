<?php
require_once __DIR__ . '/includes/data.php';

ensure_role(['admin']);

$sales = load_sales();
$metrics = calculate_dashboard_metrics($sales);
$users = load_users();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Debe ingresar un usuario y una nueva contraseña.';
    } else {
        if (update_user_password($username, $password)) {
            $message = 'Contraseña actualizada correctamente para ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $users = load_users();
        } else {
            $error = 'El usuario indicado no existe.';
        }
    }
}

$topProducts = array_slice($metrics['top_products'], 0, 5, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo Administrador</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="main-header">
    <h1>Módulo Administrador</h1>
    <nav>
        <a href="ventas.php">Ventas</a>
        <a href="compras.php">Compras</a>
        <a href="logout.php" class="logout">Cerrar sesión</a>
    </nav>
</header>
<main class="container">
    <section class="card">
        <h2>Panel de métricas</h2>
        <div class="metrics-grid">
            <div class="metric">
                <span class="metric-label">Ventas registradas</span>
                <span class="metric-value"><?= $metrics['total_sales']; ?></span>
            </div>
            <div class="metric">
                <span class="metric-label">Ingresos estimados</span>
                <span class="metric-value">$<?= number_format($metrics['total_revenue'], 2, ',', '.'); ?></span>
            </div>
            <div class="metric">
                <span class="metric-label">Gastos cargados</span>
                <span class="metric-value">$<?= number_format($metrics['total_expenses'], 2, ',', '.'); ?></span>
            </div>
            <div class="metric">
                <span class="metric-label">Compras pendientes</span>
                <span class="metric-value warning"><?= $metrics['pending_purchase']; ?></span>
            </div>
            <div class="metric">
                <span class="metric-label">Producción pendiente</span>
                <span class="metric-value warning"><?= $metrics['pending_production']; ?></span>
            </div>
        </div>
        <h3>Productos más vendidos</h3>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Unidades</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topProducts)): ?>
                    <tr><td colspan="2">Aún no hay registros suficientes.</td></tr>
                <?php else: ?>
                    <?php foreach ($topProducts as $name => $qty): ?>
                        <tr>
                            <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= $qty; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Gestión de accesos</h2>
        <?php if ($message): ?><div class="alert alert-success"><?= $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error; ?></div><?php endif; ?>
        <form method="post" class="form-inline">
            <label for="username">Usuario</label>
            <select name="username" id="username">
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($user['username'] . ' (' . $user['role'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="password">Nueva contraseña</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Actualizar</button>
        </form>
    </section>

    <section class="card">
        <h2>Actividad reciente de ventas</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Estado compras</th>
                    <th>Estado producción</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="5">Sin ventas registradas.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice(array_reverse($sales), 0, 10) as $sale): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sale['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['client']['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['status']['purchase'] ?? 'pendiente', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($sale['status']['production'] ?? 'pendiente', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>$<?= number_format((float)($sale['financial']['total_price'] ?? 0), 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
