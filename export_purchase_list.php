<?php
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/lib/SimplePDF.php';

ensure_role(['compras', 'admin']);

$date = $_GET['date'] ?? date('Y-m-d');
$sales = load_sales();
$summary = aggregate_pending_items($sales, $date);

$pdf = new SimplePDF();
$pdf->setTitle('Compras pendientes para el ' . date('d/m/Y', strtotime($date)));

if (empty($summary)) {
    $pdf->addLine('No hay compras pendientes para la fecha seleccionada.');
} else {
    foreach ($summary as $row) {
        $line = sprintf(
            '%s | %s | Talle %s | %s -> %d unidades (Imp: %d)',
            $row['material'],
            $row['color'],
            $row['size'],
            $row['garment_type'],
            $row['quantity'],
            $row['total_prints']
        );
        $pdf->addLine($line);
    }
    $pdf->addLine('');
    $pdf->addLine('Detalle por cliente:');
    foreach ($sales as $sale) {
        $createdDate = substr($sale['created_at'] ?? '', 0, 10);
        if ($createdDate !== $date) {
            continue;
        }
        $pdf->addLine($sale['client']['name'] . ' - entrega ' . ($sale['shipping']['delivery_date'] ?: 'A coordinar'));
        foreach ($sale['items'] as $item) {
            if (($item['purchase']['status'] ?? 'pending') === 'completed') {
                continue;
            }
            $pdf->addLine(sprintf('  %dx %s %s %s T%s (Imp:%d)', $item['quantity'], $item['material'], $item['color'], $item['garment_type'], $item['size'], $item['print_count']));
        }
    }
}

$pdf->output('compras_' . $date . '.pdf');
