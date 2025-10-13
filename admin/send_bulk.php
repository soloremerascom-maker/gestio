<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: index.php');
    exit;
}

$emailsRaw = $_POST['emails'] ?? '';
$label = sanitize($_POST['label'] ?? 'Invitado especial');

$emails = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $emailsRaw)));
$emails = array_unique($emails);

if (count($emails) === 0) {
    header('Location: index.php?tab=bulk-email&err=' . urlencode('Debés ingresar al menos un correo.'));
    exit;
}

if (count($emails) > 20) {
    $emails = array_slice($emails, 0, 20);
}

$created = 0;
$skipped = [];

foreach ($emails as $email) {
    $emailLower = strtolower($email);
    if (!filter_var($emailLower, FILTER_VALIDATE_EMAIL)) {
        $skipped[] = $email . ' (formato inválido)';
        continue;
    }
    if ($emailLower !== SPECIAL_TEST_EMAIL && registrationExists($emailLower)) {
        $skipped[] = $email;
        continue;
    }

    $nameParts = explode('@', $emailLower);
    $firstName = ucfirst(str_replace(['.', '-', '_'], ' ', $nameParts[0] ?? 'Invitado'));

    try {
        createRegistration([
            'profile_type' => 'especial',
            'first_name' => $firstName,
            'last_name' => '',
            'email' => $emailLower,
            'phone' => '',
            'company' => $label,
            'branch' => $label,
            'label' => $label,
        ]);
        $created++;
    } catch (Throwable $th) {
        $skipped[] = $email . ' (error: ' . $th->getMessage() . ')';
    }
}

$message = $created . ' invitaciones enviadas correctamente.';
if (!empty($skipped)) {
    $message .= ' Se omitieron ' . count($skipped) . ' registros: ' . implode(', ', $skipped) . '.';
}

header('Location: index.php?tab=bulk-email&msg=' . urlencode($message));
exit;
