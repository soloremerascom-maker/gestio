<?php
require_once __DIR__ . '/includes/data.php';

ensure_role(['compras', 'admin']);

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_purchased') {
    $saleId = $_POST['sale_id'] ?? '';
    $itemIndex = (int)($_POST['item_index'] ?? -1);
    $cost = (float)($_POST['cost'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($saleId === '' || $itemIndex < 0) {
        $error = 'Información incompleta para actualizar la compra.';
    } else {
        $updated = update_sale($saleId, function ($sale) use ($itemIndex, $cost, $notes) {
            if (!isset($sale['items'][$itemIndex])) {
                return $sale;
            }
            $sale['items'][$itemIndex]['purchase'] = [
                'status' => 'completed',
                'cost' => $cost,
                'notes' => $notes,
                'updated_at' => date('c'),
                'updated_by' => $_SESSION['user']['username'],
            ];

            // Actualizar gastos totales de la orden
            $totalExpense = 0;
            $allPurchased = true;
            foreach ($sale['items'] as $item) {
                $totalExpense += (float)($item['purchase']['cost'] ?? 0);
                if (($item['purchase']['status'] ?? 'pending') !== 'completed') {
                    $allPurchased = false;
                }
            }
            $sale['financial']['total_expense'] = $totalExpense;
            $sale['status']['purchase'] = $allPurchased ? 'completed' : 'pendiente';

            return $sale;
        });

        if ($updated) {
            $success = 'La compra fue marcada como realizada y se actualizó la venta asociada.';
        } else {
            $error = 'No se encontró la venta indicada.';
        }
    }
}

$sales = load_sales();
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$pendingSummary = aggregate_pending_items($sales, $dateFilter);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Compras</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="main-header">
    <h1>Módulo Compras</h1>
    <nav>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="admin.php">Administración</a>
        <?php endif; ?>
        <a href="ventas.php">Ventas</a>
        <a href="logout.php" class="logout">Cerrar sesión</a>
    </nav>
</header>
<main class="container">
    <section class="card">
        <h2>Órdenes de compra pendientes</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error; ?></div><?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Detalle</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasPending = false;
                foreach ($sales as $sale):
                    foreach ($sale['items'] as $index => $item):
                        if (($item['purchase']['status'] ?? 'pending') === 'completed') {
                            continue;
                        }
                        $hasPending = true;
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($sale['client']['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <?= htmlspecialchars($sale['client']['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                        <?= htmlspecialchars($sale['client']['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                        Pedido: <?= htmlspecialchars(date('d/m/Y', strtotime($sale['created_at'])), ENT_QUOTES, 'UTF-8'); ?><br>
                        Entrega: <?= htmlspecialchars($sale['shipping']['delivery_date'] ?: 'A coordinar', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <p><strong><?= htmlspecialchars($item['quantity'] . 'x ' . $item['garment_type'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p>Material: <?= htmlspecialchars($item['material'], ENT_QUOTES, 'UTF-8'); ?> | Color: <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?> | Talle: <?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Impresiones: <?= (int)$item['print_count']; ?> | Archivo: <?= htmlspecialchars($item['file_type'], ENT_QUOTES, 'UTF-8'); ?> (<?= $item['file_sent'] ? 'enviado' : 'pendiente'; ?>)</p>
                        <?php if (!empty($sale['notes'])): ?>
                            <p class="muted">Notas: <?= nl2br(htmlspecialchars($sale['notes'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" class="form-inline">
                            <input type="hidden" name="action" value="mark_purchased">
                            <input type="hidden" name="sale_id" value="<?= htmlspecialchars($sale['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="item_index" value="<?= $index; ?>">
                            <label>Costo ($)</label>
                            <input type="number" step="0.01" name="cost" required>
                            <label>Notas</label>
                            <input type="text" name="notes" placeholder="Proveedor, factura, etc">
                            <button type="submit">Marcar comprado</button>
                        </form>
                    </td>
                </tr>
                <?php
                    endforeach;
                endforeach;
                if (!$hasPending):
                ?>
                    <tr><td colspan="3">No hay compras pendientes por realizar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Resumen diario para compras</h2>
        <form method="get" class="form-inline">
            <label for="date">Fecha</label>
            <input type="date" name="date" id="date" value="<?= htmlspecialchars($dateFilter, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Actualizar</button>
            <a class="secondary" href="export_purchase_list.php?date=<?= htmlspecialchars($dateFilter, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Descargar PDF</a>
        </form>
        <table>
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Color</th>
                    <th>Talle</th>
                    <th>Tipo de prenda</th>
                    <th>Cantidad total</th>
                    <th>Impresiones totales</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingSummary)): ?>
                    <tr><td colspan="6">No hay pendientes para la fecha seleccionada.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingSummary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['material'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['size'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['garment_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int)$row['quantity']; ?></td>
                            <td><?= (int)$row['total_prints']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
