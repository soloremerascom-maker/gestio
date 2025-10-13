<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json');

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if ($code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Código vacío']);
    exit;
}

$registrations = readRegistrations();
$matched = null;

foreach ($registrations as $registration) {
    if (
        $registration['offline_code'] === $code ||
        $registration['qr_code_text'] === $code ||
        $registration['qr_filename'] === $code ||
        $registration['qr_filename'] === basename($code)
    ) {
        $matched = $registration;
        break;
    }
}

if (!$matched) {
    echo json_encode(['status' => 'not_found', 'message' => 'Código no encontrado']);
    exit;
}

if ($matched['checked_in'] === '1') {
    $person = buildPersonPayload($matched);
    [$displayName, $identifier] = composePersonHeadline($person);
    $idLabel = $identifier !== '' ? ' (DNI/Legajo: ' . $identifier . ')' : '';

    $message = $displayName . $idLabel . ' ya ingresó anteriormente.';
    if (!empty($person['checked_in_at_formatted'])) {
        $message = $displayName . $idLabel . ' ya ingresó el ' . $person['checked_in_at_formatted'] . '.';
    }

    echo json_encode([
        'status' => 'used',
        'message' => $message,
        'person' => $person,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$now = date('Y-m-d H:i:s');
$updatedRow = $matched;

$success = updateRegistration($matched['offline_code'], function ($row) use ($now, &$updatedRow) {
    $row['checked_in'] = '1';
    $row['check_in_timestamp'] = $now;
    $updatedRow = $row;
    return $row;
});

if ($success) {
    $person = buildPersonPayload($updatedRow, $now);
    [$displayName, $identifier] = composePersonHeadline($person);
    $idLabel = $identifier !== '' ? ' (DNI/Legajo: ' . $identifier . ')' : '';

    echo json_encode([
        'status' => 'ok',
        'message' => 'Acceso concedido para ' . $displayName . $idLabel . '.',
        'person' => $person,
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el registro']);
}
