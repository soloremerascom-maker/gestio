<?php
require_once __DIR__ . '/auth.php';

const DATA_SALES = __DIR__ . '/../data/sales.json';
const DATA_EMAIL_LOG = __DIR__ . '/../data/email.log';

function load_sales(): array
{
    if (!file_exists(DATA_SALES)) {
        return [];
    }
    $json = file_get_contents(DATA_SALES);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_sales(array $sales): void
{
    file_put_contents(DATA_SALES, json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function add_sale(array $sale): array
{
    $sales = load_sales();
    $sale['id'] = uniqid('sale_', true);
    $sales[] = $sale;
    save_sales($sales);
    return $sale;
}

function update_sale(string $saleId, callable $callback): ?array
{
    $sales = load_sales();
    foreach ($sales as &$sale) {
        if ($sale['id'] === $saleId) {
            $sale = $callback($sale) ?? $sale;
            save_sales($sales);
            return $sale;
        }
    }
    return null;
}

function log_email(string $to, string $subject, string $message): void
{
    $entry = sprintf("[%s] To: %s | Subject: %s\n%s\n\n", date('c'), $to, $subject, $message);
    file_put_contents(DATA_EMAIL_LOG, $entry, FILE_APPEND);
}

function calculate_dashboard_metrics(array $sales): array
{
    $metrics = [
        'total_sales' => count($sales),
        'total_revenue' => 0.0,
        'total_expenses' => 0.0,
        'pending_purchase' => 0,
        'pending_production' => 0,
        'top_products' => [],
    ];

    foreach ($sales as $sale) {
        $metrics['total_revenue'] += (float)($sale['financial']['total_price'] ?? 0);
        $metrics['total_expenses'] += (float)($sale['financial']['total_expense'] ?? 0);
        $purchaseComplete = $sale['status']['purchase'] ?? 'pending';
        $productionComplete = $sale['status']['production'] ?? 'pending';
        if ($purchaseComplete !== 'completed') {
            $metrics['pending_purchase']++;
        }
        if ($productionComplete !== 'completed') {
            $metrics['pending_production']++;
        }
        foreach ($sale['items'] as $item) {
            $key = sprintf('%s | %s | %s', $item['garment_type'], $item['material'], $item['color']);
            if (!isset($metrics['top_products'][$key])) {
                $metrics['top_products'][$key] = 0;
            }
            $metrics['top_products'][$key] += (int)$item['quantity'];
        }
    }

    arsort($metrics['top_products']);

    return $metrics;
}

function aggregate_pending_items(array $sales, ?string $date = null): array
{
    $summary = [];
    foreach ($sales as $sale) {
        $createdDate = substr($sale['created_at'] ?? '', 0, 10);
        if ($date !== null && $createdDate !== $date) {
            continue;
        }
        foreach ($sale['items'] as $index => $item) {
            $purchased = $item['purchase']['status'] ?? 'pending';
            if ($purchased === 'completed') {
                continue;
            }
            $keyParts = [
                $item['material'],
                $item['color'],
                $item['size'],
                $item['garment_type'],
            ];
            $key = implode(' | ', $keyParts);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'material' => $item['material'],
                    'color' => $item['color'],
                    'size' => $item['size'],
                    'garment_type' => $item['garment_type'],
                    'quantity' => 0,
                    'total_prints' => 0,
                ];
            }
            $summary[$key]['quantity'] += (int)$item['quantity'];
            $summary[$key]['total_prints'] += (int)($item['print_count'] ?? 0) * (int)$item['quantity'];
        }
    }
    return array_values($summary);
}
