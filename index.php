<?php
session_start();

define('USERS_DB', __DIR__ . '/db_users.json');
define('ORDERS_DB', __DIR__ . '/db_orders.json');

function load_users()
{
    if (!file_exists(USERS_DB) || filesize(USERS_DB) === 0) {
        $defaults = [
            ['username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin', 'name' => 'Administrador'],
            ['username' => 'ventas', 'password' => password_hash('ventas123', PASSWORD_DEFAULT), 'role' => 'ventas', 'name' => 'Ventas'],
            ['username' => 'compras', 'password' => password_hash('compras123', PASSWORD_DEFAULT), 'role' => 'compras', 'name' => 'Compras'],
            ['username' => 'produccion', 'password' => password_hash('prod123', PASSWORD_DEFAULT), 'role' => 'produccion', 'name' => 'Producción'],
        ];
        file_put_contents(USERS_DB, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $json = file_get_contents(USERS_DB);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_users($users)
{
    file_put_contents(USERS_DB, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function load_orders()
{
    if (!file_exists(ORDERS_DB) || filesize(ORDERS_DB) === 0) {
        file_put_contents(ORDERS_DB, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return [];
    }
    $json = file_get_contents(ORDERS_DB);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_orders($orders)
{
    file_put_contents(ORDERS_DB, json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generate_id()
{
    return uniqid('ORD');
}

function find_user($username, $users)
{
    foreach ($users as $idx => $user) {
        if ($user['username'] === $username) {
            return [$idx, $user];
        }
    }
    return [null, null];
}

function filter_orders($orders, $start, $end, $search, $quick)
{
    $startDate = $start ? strtotime($start . ' 00:00:00') : null;
    $endDate = $end ? strtotime($end . ' 23:59:59') : null;
    $search = trim(strtolower($search ?? ''));
    $now = time();

    return array_values(array_filter($orders, function ($order) use ($startDate, $endDate, $search, $quick, $now) {
        $created = isset($order['created_at']) ? strtotime($order['created_at']) : null;
        if ($startDate && $created && $created < $startDate) {
            return false;
        }
        if ($endDate && $created && $created > $endDate) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($order['id'] ?? '') . ' ' . ($order['client_name'] ?? '') . ' ' . ($order['phone'] ?? ''));
            if (strpos($haystack, $search) === false) {
                return false;
            }
        }
        if ($quick === 'purchase_delay') {
            if (($order['status'] ?? '') !== 'Pendiente de Compra') {
                return false;
            }
            if ($created && ($now - $created) <= (3 * 24 * 60 * 60)) {
                return false;
            }
        }
        if ($quick === 'delivery_delay') {
            if (($order['status'] ?? '') === 'Entregado') {
                return false;
            }
            $delivery = isset($order['delivery_date']) ? strtotime($order['delivery_date']) : null;
            if (!$delivery || ($now - $delivery) <= (5 * 24 * 60 * 60)) {
                return false;
            }
        }
        return true;
    }));
}

function calculate_order_totals($order)
{
    $itemsTotal = 0;
    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $itemsTotal += $qty * $price;
        }
    }
    $shipping = isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : 0;
    $deposit = isset($order['deposit_amount']) ? (float)$order['deposit_amount'] : 0;
    $grandTotal = $itemsTotal + $shipping;
    $pending = $grandTotal - $deposit;
    return [
        'items_total' => $itemsTotal,
        'grand_total' => $grandTotal,
        'pending' => $pending,
    ];
}

function build_query(array $base, array $overrides = [])
{
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($base[$key]);
        } else {
            $base[$key] = $value;
        }
    }
    return http_build_query($base);
}

function get_month_range()
{
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    return [$start, $end];
}

function compute_admin_metrics($orders, $start, $end)
{
    $filtered = filter_orders($orders, $start, $end, '', null);
    $delivered = array_filter($filtered, fn($order) => ($order['status'] ?? '') === 'Entregado');
    $income = 0;
    $deposit = 0;
    $pending = 0;
    $shippingCollected = 0;

    $articleCount = [];
    $paymentMethods = [];

    foreach ($delivered as $order) {
        [$itemsTotal, $grandTotal, $pendingAmount] = array_values(calculate_order_totals($order));
        $income += $grandTotal;
        $deposit += isset($order['deposit_amount']) ? (float)$order['deposit_amount'] : 0;
        $pending += $pendingAmount;
        $shippingCollected += isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : 0;

        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {
                $key = ($item['type'] ?? 'Otro');
                $articleCount[$key] = ($articleCount[$key] ?? 0) + (int)($item['quantity'] ?? 0);
            }
        }

        $method = $order['deposit_method'] ?? 'No especificado';
        $paymentMethods[$method] = ($paymentMethods[$method] ?? 0) + 1;
    }

    return [
        'income' => $income,
        'deposit' => $deposit,
        'pending' => $pending,
        'shipping' => $shippingCollected,
        'articles' => $articleCount,
        'payments' => $paymentMethods,
    ];
}

function compute_sales_metrics($orders)
{
    [$start, $end] = get_month_range();
    $filtered = filter_orders($orders, $start, $end, '', null);
    $count = count($filtered);
    $total = 0;
    foreach ($filtered as $order) {
        [$itemsTotal, $grandTotal] = array_values(calculate_order_totals($order));
        $total += $grandTotal;
    }
    return [$count, $total];
}

function compute_purchase_metrics($orders)
{
    [$start, $end] = get_month_range();
    $filtered = filter_orders($orders, $start, $end, '', null);
    $pendingOrders = array_filter($filtered, fn($order) => ($order['status'] ?? '') === 'Pendiente de Compra');
    $count = count($pendingOrders);
    $estimated = 0;
    foreach ($pendingOrders as $order) {
        $totals = calculate_order_totals($order);
        $estimated += $totals['items_total'];
    }
    return [$count, $estimated];
}

function compute_calendar($orders)
{
    $pending = array_filter($orders, fn($order) => ($order['status'] ?? '') !== 'Entregado');
    usort($pending, function ($a, $b) {
        $da = isset($a['delivery_date']) ? strtotime($a['delivery_date']) : PHP_INT_MAX;
        $db = isset($b['delivery_date']) ? strtotime($b['delivery_date']) : PHP_INT_MAX;
        return $da <=> $db;
    });
    return $pending;
}

$users = load_users();
$orders = load_orders();

$toast = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        [$idx, $user] = find_user($username, $users);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            $_SESSION['toast'] = 'Bienvenido, ' . ($user['name'] ?? $user['username']);
            header('Location: index.php');
            exit;
        }
        $toast = 'Credenciales inválidas';
    }

    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (!empty($_SESSION['user'])) {
        $currentUser = $_SESSION['user'];
        $role = $currentUser['role'];

        if (isset($_POST['action']) && $_POST['action'] === 'save_user' && $role === 'admin') {
            $targetUser = $_POST['target_user'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            [$idx, $user] = find_user($targetUser, $users);
            if ($user && $newPassword) {
                $users[$idx]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                save_users($users);
                $_SESSION['toast'] = 'Contraseña actualizada para ' . $targetUser;
            }
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['action']) && in_array($_POST['action'], ['create_order', 'update_order'], true) && in_array($role, ['ventas', 'admin'])) {
            $orderId = $_POST['order_id'] ?? null;
            $itemsJson = $_POST['items_json'] ?? '[]';
            $items = json_decode($itemsJson, true);
            if (!is_array($items)) {
                $items = [];
            }
            $data = [
                'client_name' => $_POST['client_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'delivery_date' => $_POST['delivery_date'] ?? '',
                'pickup_option' => $_POST['pickup_option'] ?? '',
                'address' => $_POST['address'] ?? '',
                'items' => $items,
                'observations' => $_POST['observations'] ?? '',
                'deposit_amount' => (float)($_POST['deposit_amount'] ?? 0),
                'deposit_method' => $_POST['deposit_method'] ?? 'No especificado',
                'shipping_cost' => (float)($_POST['shipping_cost'] ?? 0),
                'status' => $_POST['status'] ?? 'Pendiente de Compra',
            ];
            if ($_POST['action'] === 'create_order') {
                $data['id'] = generate_id();
                $data['created_at'] = date('Y-m-d');
                $data['created_by'] = $currentUser['username'];
                $orders[] = $data;
                $_SESSION['toast'] = 'Pedido creado con éxito';
            } else {
                foreach ($orders as &$order) {
                    if ($order['id'] === $orderId) {
                        $data['id'] = $order['id'];
                        $data['created_at'] = $order['created_at'];
                        $data['created_by'] = $order['created_by'];
                        $order = array_merge($order, $data);
                        $_SESSION['toast'] = 'Pedido actualizado correctamente';
                        break;
                    }
                }
                unset($order);
            }
            save_orders($orders);
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_order' && in_array($role, ['ventas', 'admin', 'compras'])) {
            $orderId = $_POST['order_id'] ?? '';
            foreach ($orders as $idx => $order) {
                if ($order['id'] === $orderId) {
                    if (($order['status'] ?? '') === 'Pendiente de Compra') {
                        unset($orders[$idx]);
                        $orders = array_values($orders);
                        save_orders($orders);
                        $_SESSION['toast'] = 'Pedido eliminado';
                    } else {
                        $_SESSION['toast'] = 'Solo se pueden eliminar pedidos pendientes de compra';
                    }
                    break;
                }
            }
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
            $orderId = $_POST['order_id'] ?? '';
            $newStatus = $_POST['new_status'] ?? '';
            foreach ($orders as &$order) {
                if ($order['id'] === $orderId) {
                    $order['status'] = $newStatus;
                    if ($newStatus === 'Entregado') {
                        $order['delivered_at'] = date('Y-m-d');
                    }
                    $_SESSION['toast'] = 'Estado actualizado a ' . $newStatus;
                    break;
                }
            }
            unset($order);
            save_orders($orders);
            header('Location: index.php');
            exit;
        }
    }
}

if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

$loggedUser = $_SESSION['user'] ?? null;

$startFilter = $_GET['start_date'] ?? '';
$endFilter = $_GET['end_date'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$quickFilter = $_GET['quick'] ?? null;

$filteredOrders = filter_orders($orders, $startFilter, $endFilter, $searchFilter, $quickFilter);
$dateFilteredOrders = filter_orders($orders, $startFilter, $endFilter, '', null);
$calendarOrders = compute_calendar($dateFilteredOrders);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Remeras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-pb3VMJ+m3ZB+YB5hW9jBmqI+qdVv+PQK4P0Hzfdwwc9FTe74MkRU2w35vj0IadB1z7mwhhtj6Vxz0YBb7X0XQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6fb; }
        .sidebar { min-width: 260px; }
        .toast {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 1024px) {
            .sidebar { position: fixed; z-index: 40; top: 0; left: 0; height: 100vh; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body class="min-h-screen">
<?php if (!$loggedUser): ?>
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="bg-white shadow-xl rounded-xl p-10 w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Gestión Integral</h1>
                <p class="text-gray-500 mt-2">Accede a tu panel con tus credenciales</p>
            </div>
            <form method="post" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-sm font-medium text-gray-600">Usuario</label>
                    <input type="text" name="username" class="mt-1 w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring focus:ring-indigo-200" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Contraseña</label>
                    <input type="password" name="password" class="mt-1 w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring focus:ring-indigo-200" required>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition">Iniciar Sesión</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <?php $role = $loggedUser['role']; ?>
    <div class="flex min-h-screen">
        <div id="sidebar" class="sidebar bg-indigo-700 text-white p-6 flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Gestión</h2>
                <button class="lg:hidden" id="closeSidebar"><i class="fas fa-times"></i></button>
            </div>
            <nav class="flex-1 space-y-3">
                <a href="#dashboard" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-chart-line"></i><span>Dashboard</span></a>
                <a href="#orders" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-list"></i><span>Pedidos</span></a>
                <?php if (in_array($role, ['ventas', 'produccion'])): ?>
                    <a href="#deliveries" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-truck"></i><span>Pendientes de Entrega</span></a>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                    <a href="#users" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-users-cog"></i><span>Usuarios</span></a>
                <?php endif; ?>
                <?php if ($role === 'compras'): ?>
                    <a href="#purchase" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-cart-shopping"></i><span>Compras</span></a>
                <?php endif; ?>
                <?php if ($role === 'produccion'): ?>
                    <a href="#production" class="flex items-center gap-3 p-3 rounded-lg hover:bg-indigo-600 transition"><i class="fas fa-gears"></i><span>Producción</span></a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex-1 flex flex-col">
            <header class="bg-white shadow px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-indigo-700" id="openSidebar"><i class="fas fa-bars"></i></button>
                    <h1 class="text-2xl font-bold text-gray-800">Hola, <?php echo htmlspecialchars($loggedUser['name'] ?? $loggedUser['username']); ?></h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 capitalize">Rol: <?php echo htmlspecialchars($role); ?></span>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg"><i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión</button>
                    </form>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto p-6 space-y-10">
                <section id="dashboard" class="bg-white rounded-xl shadow p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>
                        <div class="flex items-center gap-3">
                            <form method="get" class="flex items-center gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Desde</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startFilter); ?>" class="border rounded-lg px-3 py-1">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Hasta</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endFilter); ?>" class="border rounded-lg px-3 py-1">
                                </div>
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Filtrar</button>
                            </form>
                            <form method="get">
                                <button class="text-sm text-gray-500">Limpiar</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($role === 'admin'): ?>
                        <?php $metrics = compute_admin_metrics($orders, $startFilter, $endFilter); ?>
                        <div class="grid md:grid-cols-4 gap-4">
                            <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
                                <h3 class="text-sm text-indigo-600">Ingresos Totales</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($metrics['income'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-green-50 border border-green-100 rounded-lg p-4">
                                <h3 class="text-sm text-green-600">Señas Recibidas</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($metrics['deposit'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-4">
                                <h3 class="text-sm text-yellow-600">Pendiente de Cobro</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($metrics['pending'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-purple-50 border border-purple-100 rounded-lg p-4">
                                <h3 class="text-sm text-purple-600">Envíos Cobrados</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($metrics['shipping'], 2, ',', '.'); ?></p>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-700 mb-4">Artículos más Vendidos</h3>
                                <canvas id="chartArticles"></canvas>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-700 mb-4">Métodos de Pago más Utilizados</h3>
                                <canvas id="chartPayments"></canvas>
                            </div>
                        </div>
                    <?php elseif ($role === 'ventas'): ?>
                        <?php [$salesCount, $salesTotal] = compute_sales_metrics($orders); ?>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
                                <h3 class="text-sm text-indigo-600">Ventas del Mes</h3>
                                <p class="text-3xl font-bold"><?php echo $salesCount; ?></p>
                            </div>
                            <div class="bg-green-50 border border-green-100 rounded-lg p-4">
                                <h3 class="text-sm text-green-600">Total Facturado del Mes</h3>
                                <p class="text-3xl font-bold">$<?php echo number_format($salesTotal, 2, ',', '.'); ?></p>
                            </div>
                        </div>
                    <?php elseif ($role === 'compras'): ?>
                        <?php [$pendingCount, $estimated] = compute_purchase_metrics($orders); ?>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-4">
                                <h3 class="text-sm text-yellow-600">Compras Pendientes del Mes</h3>
                                <p class="text-3xl font-bold"><?php echo $pendingCount; ?></p>
                            </div>
                            <div class="bg-purple-50 border border-purple-100 rounded-lg p-4">
                                <h3 class="text-sm text-purple-600">Gastos Estimados del Mes</h3>
                                <p class="text-3xl font-bold">$<?php echo number_format($estimated, 2, ',', '.'); ?></p>
                            </div>
                        </div>
                    <?php elseif ($role === 'produccion'): ?>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                                <h3 class="text-sm text-blue-600">Producción Pendiente</h3>
                                <p class="text-3xl font-bold"><?php echo count(array_filter($dateFilteredOrders, fn($order) => ($order['status'] ?? '') === 'Listo para Producir')); ?></p>
                            </div>
                            <div class="bg-green-50 border border-green-100 rounded-lg p-4">
                                <h3 class="text-sm text-green-600">Pedidos Completados Este Mes</h3>
                                <p class="text-3xl font-bold"><?php echo count(array_filter($orders, function ($order) {
                                    return ($order['status'] ?? '') === 'Completado' && strpos($order['created_at'] ?? '', date('Y-m')) === 0;
                                })); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <section id="orders" class="bg-white rounded-xl shadow p-6 space-y-6">
                    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-2 lg:mb-0">Pedidos</h2>
                            <form method="get" class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Desde</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Hasta</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <div class="min-w-[200px]">
                                    <label class="text-xs text-gray-500">Buscar</label>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>" placeholder="ID, cliente o teléfono" class="border rounded-lg px-3 py-2 w-full">
                                </div>
                                <input type="hidden" name="quick" value="<?php echo htmlspecialchars($quickFilter ?? ''); ?>">
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Aplicar</button>
                                <a href="index.php" class="px-4 py-2 rounded-lg border">Limpiar</a>
                            </form>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="?<?php echo build_query($_GET, ['quick' => 'purchase_delay']); ?>" class="bg-red-100 text-red-600 px-3 py-2 rounded-lg text-sm"><i class="fas fa-clock mr-1"></i>Atraso Compra</a>
                            <a href="?<?php echo build_query($_GET, ['quick' => 'delivery_delay']); ?>" class="bg-orange-100 text-orange-600 px-3 py-2 rounded-lg text-sm"><i class="fas fa-truck mr-1"></i>Atraso Entrega</a>
                            <?php if (in_array($role, ['ventas', 'admin'])): ?>
                                <button id="openOrderModal" class="bg-indigo-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-plus"></i> Cargar Nueva Venta</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left">
                                <tr>
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">Cliente</th>
                                    <th class="px-4 py-2">Teléfono</th>
                                    <th class="px-4 py-2">Entrega</th>
                                    <th class="px-4 py-2">Estado</th>
                                    <th class="px-4 py-2">Total</th>
                                    <th class="px-4 py-2">Compras</th>
                                    <th class="px-4 py-2">Producción</th>
                                    <th class="px-4 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($filteredOrders)): ?>
                                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Sin pedidos con los filtros aplicados.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($filteredOrders as $order): ?>
                                    <?php $totals = calculate_order_totals($order); ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($order['id']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($order['client_name']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($order['phone']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($order['delivery_date']); ?></td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                        <td class="px-4 py-3">$<?php echo number_format($totals['grand_total'], 2, ',', '.'); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if (($order['status'] ?? '') === 'Pendiente de Compra'): ?>
                                                <i class="fas fa-xmark text-red-500"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check text-green-500"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if (in_array($order['status'], ['Completado', 'Entregado'])): ?>
                                                <i class="fas fa-check text-green-500"></i>
                                            <?php else: ?>
                                                <i class="fas fa-xmark text-red-500"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <button class="text-indigo-600 hover:underline edit-order" data-order='<?php echo json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'><i class="fas fa-pen"></i></button>
                                            <?php if (in_array($role, ['ventas', 'admin', 'compras'])): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('¿Eliminar pedido?');">
                                                    <input type="hidden" name="action" value="delete_order">
                                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                                    <button class="text-red-500"><i class="fas fa-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="bg-white rounded-xl shadow p-6 space-y-4">
                    <h2 class="text-xl font-semibold text-gray-800">Calendario de Entregas</h2>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if (empty($calendarOrders)): ?>
                            <p class="text-gray-500">No hay pedidos pendientes de entrega.</p>
                        <?php endif; ?>
                        <?php foreach ($calendarOrders as $order): ?>
                            <?php $delivery = $order['delivery_date'] ?? 'Sin fecha';
                            $status = $order['status'] ?? '';
                            $urgency = 'bg-gray-50 border-gray-200';
                            if ($status !== 'Entregado') {
                                $days = strtotime($delivery) - time();
                                if ($days <= 0) {
                                    $urgency = 'bg-red-50 border-red-200';
                                } elseif ($days <= 2 * 24 * 3600) {
                                    $urgency = 'bg-yellow-50 border-yellow-200';
                                } else {
                                    $urgency = 'bg-green-50 border-green-200';
                                }
                            }
                            ?>
                            <div class="border <?php echo $urgency; ?> rounded-lg p-4 space-y-2">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['client_name']); ?></h3>
                                    <span class="text-xs uppercase tracking-wide bg-white px-2 py-1 rounded-full"><?php echo htmlspecialchars($status); ?></span>
                                </div>
                                <p class="text-sm text-gray-500"><i class="fas fa-calendar mr-1"></i> <?php echo htmlspecialchars($delivery); ?></p>
                                <p class="text-sm text-gray-500"><i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($order['phone']); ?></p>
                                <p class="text-sm text-gray-500"><i class="fas fa-location-dot mr-1"></i> <?php echo htmlspecialchars($order['pickup_option']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($role === 'admin'): ?>
                    <section id="users" class="bg-white rounded-xl shadow p-6 space-y-6">
                        <h2 class="text-xl font-semibold text-gray-800">Gestión de Usuarios</h2>
                        <form method="post" class="grid md:grid-cols-3 gap-4 items-end">
                            <input type="hidden" name="action" value="save_user">
                            <div>
                                <label class="text-sm text-gray-600">Usuario</label>
                                <select name="target_user" class="border rounded-lg px-3 py-2 w-full" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['username']); ?>"><?php echo htmlspecialchars($user['username'] . ' (' . $user['role'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">Nueva Contraseña</label>
                                <input type="password" name="new_password" class="border rounded-lg px-3 py-2 w-full" required>
                            </div>
                            <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Actualizar</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if ($role === 'compras'): ?>
                    <section id="purchase" class="bg-white rounded-xl shadow p-6 space-y-6">
                        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                            <h2 class="text-xl font-semibold text-gray-800">Pedidos Pendientes de Compra</h2>
                            <form method="get" class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Desde</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Hasta</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
                                <input type="hidden" name="quick" value="<?php echo htmlspecialchars($quickFilter ?? ''); ?>">
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Aplicar</button>
                                <a href="index.php" class="px-4 py-2 rounded-lg border">Limpiar</a>
                            </form>
                        </div>
                        <div class="grid gap-4">
                            <?php $pending = array_filter($dateFilteredOrders, fn($order) => ($order['status'] ?? '') === 'Pendiente de Compra'); ?>
                            <?php if (empty($pending)): ?>
                                <p class="text-gray-500">No hay pedidos pendientes.</p>
                            <?php endif; ?>
                            <?php foreach ($pending as $order): ?>
                                <?php $totals = calculate_order_totals($order); ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['client_name']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['phone']); ?> · Entrega: <?php echo htmlspecialchars($order['delivery_date']); ?></p>
                                        </div>
                                        <p class="text-lg font-bold text-gray-700">$<?php echo number_format($totals['items_total'], 2, ',', '.'); ?></p>
                                    </div>
                                    <div class="mt-4 flex items-center gap-3">
                                        <form method="post">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                            <input type="hidden" name="new_status" value="Listo para Producir">
                                            <button class="bg-green-600 text-white px-4 py-2 rounded-lg">Marcar Comprado</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('¿Eliminar pedido?');">
                                            <input type="hidden" name="action" value="delete_order">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                            <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($role === 'produccion'): ?>
                    <section id="production" class="bg-white rounded-xl shadow p-6 space-y-6">
                        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                            <h2 class="text-xl font-semibold text-gray-800">Producción Pendiente</h2>
                            <form method="get" class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Desde</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Hasta</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
                                <input type="hidden" name="quick" value="<?php echo htmlspecialchars($quickFilter ?? ''); ?>">
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Aplicar</button>
                                <a href="index.php" class="px-4 py-2 rounded-lg border">Limpiar</a>
                            </form>
                        </div>
                        <div class="grid gap-4">
                            <?php $toProduce = array_filter($dateFilteredOrders, fn($order) => ($order['status'] ?? '') === 'Listo para Producir'); ?>
                            <?php if (empty($toProduce)): ?>
                                <p class="text-gray-500">No hay pedidos listos para producir.</p>
                            <?php endif; ?>
                            <?php foreach ($toProduce as $order): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['client_name']); ?></h3>
                                            <p class="text-sm text-gray-500">Entrega: <?php echo htmlspecialchars($order['delivery_date']); ?></p>
                                        </div>
                                        <form method="post">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                            <input type="hidden" name="new_status" value="Completado">
                                            <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Marcar Completado</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (in_array($role, ['ventas', 'produccion'])): ?>
                    <section id="deliveries" class="bg-white rounded-xl shadow p-6 space-y-6">
                        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                            <h2 class="text-xl font-semibold text-gray-800">Pendientes de Entrega</h2>
                            <form method="get" class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Desde</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Hasta</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endFilter); ?>" class="border rounded-lg px-3 py-2">
                                </div>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
                                <input type="hidden" name="quick" value="<?php echo htmlspecialchars($quickFilter ?? ''); ?>">
                                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Aplicar</button>
                                <a href="index.php" class="px-4 py-2 rounded-lg border">Limpiar</a>
                            </form>
                        </div>
                        <div class="grid gap-4">
                            <?php $toDeliver = array_filter($dateFilteredOrders, fn($order) => ($order['status'] ?? '') === 'Completado'); ?>
                            <?php if (empty($toDeliver)): ?>
                                <p class="text-gray-500">No hay pedidos completados para entregar.</p>
                            <?php endif; ?>
                            <?php foreach ($toDeliver as $order): ?>
                                <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['client_name']); ?></h3>
                                        <p class="text-sm text-gray-500">Entrega: <?php echo htmlspecialchars($order['delivery_date']); ?> · Punto: <?php echo htmlspecialchars($order['pickup_option']); ?></p>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                        <input type="hidden" name="new_status" value="Entregado">
                                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg">Marcar Entregado</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div id="orderModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 relative">
            <button id="closeOrderModal" class="absolute top-4 right-4 text-gray-500"><i class="fas fa-times"></i></button>
            <h2 id="orderModalTitle" class="text-2xl font-semibold text-gray-800 mb-4">Nuevo Pedido</h2>
            <form id="orderForm" method="post" class="space-y-4">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" name="order_id" id="orderIdField">
                <input type="hidden" name="items_json" id="itemsJson">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">Nombre Cliente</label>
                        <input type="text" name="client_name" class="border rounded-lg px-3 py-2 w-full" required>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Teléfono</label>
                        <input type="text" name="phone" class="border rounded-lg px-3 py-2 w-full" required>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Fecha de Entrega</label>
                        <input type="date" name="delivery_date" class="border rounded-lg px-3 py-2 w-full" required>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Estado</label>
                        <select name="status" class="border rounded-lg px-3 py-2 w-full">
                            <option value="Pendiente de Compra">Pendiente de Compra</option>
                            <option value="Listo para Producir">Listo para Producir</option>
                            <option value="Completado">Completado</option>
                            <option value="Entregado">Entregado</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm text-gray-600">Punto de Retiro / Envío</label>
                        <select name="pickup_option" id="pickupOption" class="border rounded-lg px-3 py-2 w-full" required>
                            <option value="Caballito">Caballito</option>
                            <option value="Recoleta">Recoleta</option>
                            <option value="Barracas">Barracas</option>
                            <option value="Floresta">Floresta</option>
                            <option value="Envío a Domicilio">Envío a Domicilio</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 hidden" id="addressField">
                        <label class="text-sm text-gray-600">Dirección</label>
                        <input type="text" name="address" class="border rounded-lg px-3 py-2 w-full">
                    </div>
                </div>
                <div class="border rounded-lg p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-700">Artículos</h3>
                        <button type="button" id="addItem" class="bg-indigo-600 text-white px-4 py-2 rounded-lg"><i class="fas fa-plus mr-1"></i>Agregar artículo</button>
                    </div>
                    <div id="itemsContainer" class="space-y-4"></div>
                    <div class="flex items-center justify-between text-lg font-semibold text-gray-700">
                        <span>Total Artículos:</span>
                        <span id="itemsTotal">$0.00</span>
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">Monto Seña</label>
                        <input type="number" step="0.01" name="deposit_amount" class="border rounded-lg px-3 py-2 w-full" value="0">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Forma de Pago Seña</label>
                        <select name="deposit_method" class="border rounded-lg px-3 py-2 w-full">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta de Crédito">Tarjeta de Crédito</option>
                            <option value="GoCuotas">GoCuotas</option>
                            <option value="Mercado Pago">Mercado Pago</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Costo Envío</label>
                        <input type="number" step="0.01" name="shipping_cost" class="border rounded-lg px-3 py-2 w-full" value="0">
                    </div>
                </div>
                <div>
                    <label class="text-sm text-gray-600 flex items-center gap-2">Observaciones <button type="button" id="voiceButton" class="text-indigo-600"><i class="fas fa-microphone"></i></button></label>
                    <textarea name="observations" rows="3" class="border rounded-lg px-3 py-2 w-full"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelModal" class="px-4 py-2 rounded-lg border">Cancelar</button>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar Pedido</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div id="toastContainer" class="fixed bottom-6 right-6 space-y-3 z-50"></div>

<script>
const toastMessage = <?php echo json_encode($toast); ?>;
const orderModal = document.getElementById('orderModal');
const openOrderModalBtn = document.getElementById('openOrderModal');
const closeOrderModalBtn = document.getElementById('closeOrderModal');
const cancelModalBtn = document.getElementById('cancelModal');
const orderForm = document.getElementById('orderForm');
const formActionField = orderForm ? orderForm.querySelector('input[name="action"]') : null;
const itemsContainer = document.getElementById('itemsContainer');
const itemsJson = document.getElementById('itemsJson');
const itemsTotalLabel = document.getElementById('itemsTotal');
const pickupOption = document.getElementById('pickupOption');
const addressField = document.getElementById('addressField');
const orderModalTitle = document.getElementById('orderModalTitle');
const orderIdField = document.getElementById('orderIdField');

const itemTypes = ['Remera clásica', 'Oversize', 'Talle especial', 'Taza', 'Body bebé', 'Buzo', 'Gorra'];
const colors = ['Blanco', 'Negro', 'Rojo', 'Azul', 'Verde', 'Amarillo', 'Rosa'];
const sizes = ['1', '2', '3', '4', '5', '6', 'S', 'M', 'L', 'XL', 'XXL'];

function showToast(message) {
    if (!message) return;
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast bg-white shadow-lg border border-gray-200 px-4 py-3 rounded-lg flex items-center gap-3';
    toast.innerHTML = `<i class="fas fa-check-circle text-green-500"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('opacity-0');
        toast.addEventListener('transitionend', () => toast.remove());
    }, 3000);
}

function toggleModal(show) {
    if (!orderModal) return;
    if (show) {
        orderModal.classList.remove('hidden');
        orderModal.classList.add('flex');
    } else {
        orderModal.classList.add('hidden');
        orderModal.classList.remove('flex');
        if (orderForm) {
            orderForm.reset();
            if (formActionField) formActionField.value = 'create_order';
        }
        itemsContainer.innerHTML = '';
        updateItemsTotal();
        orderModalTitle.textContent = 'Nuevo Pedido';
        orderIdField.value = '';
        addressField.classList.add('hidden');
    }
}

function updateItemsTotal() {
    let total = 0;
    const rows = itemsContainer.querySelectorAll('.item-row');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('[name="quantity"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="unit_price"]').value) || 0;
        total += qty * price;
    });
    itemsTotalLabel.textContent = '$' + total.toFixed(2);
}

function serializeItems() {
    const rows = itemsContainer.querySelectorAll('.item-row');
    const items = [];
    rows.forEach(row => {
        items.push({
            type: row.querySelector('[name="type"]').value,
            color: row.querySelector('[name="color"]').value,
            size: row.querySelector('[name="size"]').value,
            quantity: row.querySelector('[name="quantity"]').value,
            unit_price: row.querySelector('[name="unit_price"]').value
        });
    });
    itemsJson.value = JSON.stringify(items);
}

function addItemRow(item = null) {
    const row = document.createElement('div');
    row.className = 'item-row border border-gray-200 rounded-lg p-3 grid md:grid-cols-6 gap-2';
    row.innerHTML = `
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500">Tipo de Prenda</label>
            <select name="type" class="border rounded-lg px-3 py-2 w-full">${itemTypes.map(type => `<option value="${type}">${type}</option>`).join('')}</select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Color</label>
            <select name="color" class="border rounded-lg px-3 py-2 w-full">${colors.map(color => `<option value="${color}">${color}</option>`).join('')}</select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Talle</label>
            <select name="size" class="border rounded-lg px-3 py-2 w-full">${sizes.map(size => `<option value="${size}">${size}</option>`).join('')}</select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Cantidad</label>
            <input type="number" name="quantity" min="1" value="1" class="border rounded-lg px-3 py-2 w-full">
        </div>
        <div>
            <label class="text-xs text-gray-500">Precio Unitario</label>
            <input type="number" name="unit_price" min="0" step="0.01" value="0" class="border rounded-lg px-3 py-2 w-full">
        </div>
        <div class="flex items-end">
            <button type="button" class="remove-item text-red-500"><i class="fas fa-trash"></i></button>
        </div>
    `;
    itemsContainer.appendChild(row);
    if (item) {
        row.querySelector('[name="type"]').value = item.type || '';
        row.querySelector('[name="color"]').value = item.color || '';
        row.querySelector('[name="size"]').value = item.size || '';
        row.querySelector('[name="quantity"]').value = item.quantity || 1;
        row.querySelector('[name="unit_price"]').value = item.unit_price || 0;
    }
    row.querySelectorAll('input, select').forEach(input => input.addEventListener('input', updateItemsTotal));
    row.querySelector('.remove-item').addEventListener('click', () => {
        row.remove();
        updateItemsTotal();
    });
    updateItemsTotal();
}

if (openOrderModalBtn) {
    openOrderModalBtn.addEventListener('click', () => {
        toggleModal(true);
        addItemRow();
    });
}
if (closeOrderModalBtn) closeOrderModalBtn.addEventListener('click', () => toggleModal(false));
if (cancelModalBtn) cancelModalBtn.addEventListener('click', () => toggleModal(false));

orderForm?.addEventListener('submit', (event) => {
    serializeItems();
});

document.getElementById('addItem')?.addEventListener('click', () => addItemRow());

pickupOption?.addEventListener('change', () => {
    if (pickupOption.value === 'Envío a Domicilio') {
        addressField.classList.remove('hidden');
    } else {
        addressField.classList.add('hidden');
    }
});

if (pickupOption && pickupOption.value === 'Envío a Domicilio') {
    addressField.classList.remove('hidden');
}

document.querySelectorAll('.edit-order').forEach(btn => {
    btn.addEventListener('click', () => {
        const order = JSON.parse(btn.dataset.order);
        toggleModal(true);
        if (formActionField) formActionField.value = 'update_order';
        orderModalTitle.textContent = 'Editar Pedido';
        orderIdField.value = order.id;
        orderForm.client_name.value = order.client_name || '';
        orderForm.phone.value = order.phone || '';
        orderForm.delivery_date.value = order.delivery_date || '';
        orderForm.pickup_option.value = order.pickup_option || 'Caballito';
        orderForm.status.value = order.status || 'Pendiente de Compra';
        orderForm.address.value = order.address || '';
        orderForm.deposit_amount.value = order.deposit_amount || 0;
        orderForm.deposit_method.value = order.deposit_method || 'Efectivo';
        orderForm.shipping_cost.value = order.shipping_cost || 0;
        orderForm.observations.value = order.observations || '';
        addressField.classList.toggle('hidden', order.pickup_option !== 'Envío a Domicilio');
        itemsContainer.innerHTML = '';
        (order.items || []).forEach(item => addItemRow(item));
        if (!order.items || order.items.length === 0) addItemRow();
        updateItemsTotal();
    });
});

const voiceButton = document.getElementById('voiceButton');
if (voiceButton && 'webkitSpeechRecognition' in window) {
    const recognition = new webkitSpeechRecognition();
    recognition.lang = 'es-AR';
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        orderForm.observations.value += (orderForm.observations.value ? ' ' : '') + transcript;
    };
    voiceButton.addEventListener('click', () => {
        recognition.start();
        showToast('Escuchando...');
    });
}

const sidebar = document.getElementById('sidebar');
const openSidebar = document.getElementById('openSidebar');
const closeSidebar = document.getElementById('closeSidebar');
openSidebar?.addEventListener('click', () => sidebar.classList.add('show'));
closeSidebar?.addEventListener('click', () => sidebar.classList.remove('show'));
document.addEventListener('click', (event) => {
    if (!sidebar.contains(event.target) && event.target !== openSidebar && window.innerWidth < 1024) {
        sidebar.classList.remove('show');
    }
});

showToast(toastMessage);

<?php if ($loggedUser && $role === 'admin'): ?>
const articleCtx = document.getElementById('chartArticles');
const paymentCtx = document.getElementById('chartPayments');
const articleData = <?php echo json_encode($metrics['articles']); ?>;
const paymentData = <?php echo json_encode($metrics['payments']); ?>;
if (articleCtx && Object.keys(articleData).length > 0) {
    new Chart(articleCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(articleData),
            datasets: [{
                label: 'Cantidad',
                data: Object.values(articleData),
                backgroundColor: '#6366f1'
            }]
        }
    });
}
if (paymentCtx && Object.keys(paymentData).length > 0) {
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(paymentData),
            datasets: [{
                label: 'Pagos',
                data: Object.values(paymentData),
                backgroundColor: ['#a855f7', '#f97316', '#22c55e', '#0ea5e9', '#facc15']
            }]
        }
    });
}
<?php endif; ?>
</script>
</body>
</html>
