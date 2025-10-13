<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: index.php');
    exit;
}

$quantity = max(1, min(50, (int)($_POST['quantity'] ?? 1)));
$notes = sanitize($_POST['notes'] ?? 'Invitado especial');

$batch = [];

for ($i = 1; $i <= $quantity; $i++) {
    try {
        $record = createRegistration([
            'profile_type' => 'especial',
            'first_name' => 'Invitado',
            'last_name' => '#' . $i,
            'email' => '',
            'phone' => '',
            'company' => $notes,
            'branch' => $notes,
            'label' => $notes,
        ], false);
        $batch[] = $record;
    } catch (Throwable $th) {
        header('Location: index.php?tab=bulk-qr&err=' . urlencode('Error al generar códigos: ' . $th->getMessage()));
        exit;
    }
}

$_SESSION['generated_qr_batch'] = $batch;

header('Location: index.php?tab=bulk-qr&msg=' . urlencode('Se generaron ' . $quantity . ' códigos.'));
exit;
