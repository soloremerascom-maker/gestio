<?php
require_once __DIR__ . '/../config.php';

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function load_json(string $filePath, $default)
{
    if (!file_exists($filePath)) {
        return $default;
    }

    $data = json_decode((string)file_get_contents($filePath), true);

    if ($data === null || !is_array($data)) {
        return $default;
    }

    return $data;
}

function save_json(string $filePath, $data): void
{
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function require_login(): void
{
    ensure_session();
    if (!isset($_SESSION['user'])) {
        header('Location: /index.php');
        exit;
    }
}

function current_user(): ?array
{
    ensure_session();
    return $_SESSION['user'] ?? null;
}

function authorize(array $roles): void
{
    require_login();
    $user = current_user();
    if ($user === null || (!empty($roles) && !in_array($user['role'], $roles, true))) {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
}

function load_orders(): array
{
    return load_json(ORDERS_FILE, []);
}

function save_orders(array $orders): void
{
    save_json(ORDERS_FILE, $orders);
}

function load_users(): array
{
    return load_json(USERS_FILE, []);
}

function save_users(array $users): void
{
    save_json(USERS_FILE, $users);
}

function find_order_index(array $orders, string $orderId): ?int
{
    foreach ($orders as $index => $order) {
        if (($order['id'] ?? '') === $orderId) {
            return $index;
        }
    }
    return null;
}

function format_currency(float $amount): string
{
    return '$' . number_format($amount, 2, ',', '.');
}

function aggregate_items(array $orders): array
{
    $summary = [];
    foreach ($orders as $order) {
        if (($order['purchase_status'] ?? 'pendiente') === 'completado') {
            continue;
        }
        foreach ($order['items'] ?? [] as $item) {
            $key = strtolower(($item['type'] ?? '')) . '|' .
                strtolower(($item['fabric'] ?? '')) . '|' .
                strtolower(($item['color'] ?? '')) . '|' .
                strtoupper(($item['size'] ?? ''));

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'type' => $item['type'] ?? 'N/D',
                    'fabric' => $item['fabric'] ?? 'N/D',
                    'color' => $item['color'] ?? 'N/D',
                    'size' => $item['size'] ?? 'N/D',
                    'quantity' => 0,
                    'total_prints' => 0,
                ];
            }

            $summary[$key]['quantity'] += (int)($item['quantity'] ?? 0);
            $summary[$key]['total_prints'] += (int)($item['print_count'] ?? 0) * (int)($item['quantity'] ?? 0);
        }
    }

    return array_values($summary);
}

function calculate_metrics(array $orders): array
{
    $ingresos = 0.0;
    $gastos = 0.0;
    $egresos = 0.0;
    $topItems = [];

    foreach ($orders as $order) {
        $ingresos += (float)($order['total_amount'] ?? 0);
        if (($order['purchase_status'] ?? 'pendiente') === 'completado') {
            $gastos += (float)($order['purchase_cost'] ?? 0);
        } else {
            $egresos += (float)($order['purchase_cost'] ?? 0);
        }

        foreach ($order['items'] ?? [] as $item) {
            $key = strtolower(($item['type'] ?? 'N/D'));
            if (!isset($topItems[$key])) {
                $topItems[$key] = 0;
            }
            $topItems[$key] += (int)($item['quantity'] ?? 0);
        }
    }

    arsort($topItems);

    return [
        'ingresos' => $ingresos,
        'gastos' => $gastos,
        'egresos' => $egresos,
        'top_items' => array_slice($topItems, 0, 5, true),
        'totales' => [
            'ordenes' => count($orders),
            'pendientes_compra' => count(array_filter($orders, fn($o) => ($o['purchase_status'] ?? 'pendiente') !== 'completado')),
            'pendientes_produccion' => count(array_filter($orders, fn($o) => ($o['production_status'] ?? 'pendiente') !== 'entregado')),
        ],
    ];
}
