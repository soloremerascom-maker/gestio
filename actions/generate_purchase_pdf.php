<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin', 'compras']);

$orders = load_orders();
$summary = aggregate_items($orders);
$pendingOrders = array_filter($orders, fn($o) => ($o['purchase_status'] ?? 'pendiente') !== 'completado');

$lines = [];
$lines[] = 'Lista consolidada de compras';
$lines[] = 'Generado: ' . date('d/m/Y H:i');
$lines[] = 'Órdenes pendientes: ' . count($pendingOrders);
$lines[] = '';
$lines[] = 'Totales por prenda:';
if (empty($summary)) {
    $lines[] = '- No hay compras pendientes';
} else {
    foreach ($summary as $item) {
        $lines[] = sprintf(
            '- %d x %s %s %s (Talle %s) | Impresiones totales: %d',
            (int)$item['quantity'],
            $item['fabric'],
            $item['color'],
            $item['type'],
            $item['size'],
            (int)$item['total_prints']
        );
    }
}
$lines[] = '';
$lines[] = 'Detalle por cliente:';
if (empty($pendingOrders)) {
    $lines[] = '- Sin órdenes abiertas';
} else {
    foreach ($pendingOrders as $order) {
        $lines[] = sprintf('%s - entrega %s', $order['client']['name'], $order['delivery']['date']);
        foreach ($order['items'] as $item) {
            $lines[] = sprintf(
                '    * %d x %s %s %s T%s (impresiones: %d)',
                (int)$item['quantity'],
                $item['fabric'],
                $item['color'],
                $item['type'],
                $item['size'],
                (int)$item['print_count']
            );
        }
    }
}

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$contentParts = [];
$y = 800;
foreach ($lines as $line) {
    if ($y < 80) {
        // No soportamos múltiples páginas en este ejemplo
        break;
    }
    $contentParts[] = sprintf("BT /F1 12 Tf 50 %d Td (%s) Tj ET", $y, pdf_escape($line));
    $y -= 18;
}
$content = implode("\n", $contentParts);
$length = strlen($content);

$pdf = "%PDF-1.4\n";
$offsets = [];
$objects = [
    "<< /Type /Catalog /Pages 2 0 R >>",
    "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
    "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
    "<< /Length $length >>\nstream\n$content\nendstream",
    "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
];

for ($i = 0; $i < count($objects); $i++) {
    $offsets[$i + 1] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
}

$xrefPosition = strlen($pdf);
$pdf .= "xref\n0 6\n0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="lista-compras-' . date('Ymd-His') . '.pdf"');
echo $pdf;
