<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json');

function buildPersonPayload(array $record, ?string $overrideTimestamp = null): array
{
    $firstName = (string) ($record['first_name'] ?? '');
    $lastName = (string) ($record['last_name'] ?? '');
    $profileType = (string) ($record['profile_type'] ?? '');
    $branch = (string) ($record['branch'] ?? '');
    $company = (string) ($record['company'] ?? '');

    $location = '';
    if ($profileType === 'empleado') {
        $location = $branch;
    } elseif ($profileType === 'proveedor') {
        $location = $company;
    } else {
        $location = $branch ?: ($company ?: 'Invitado especial');
    }

    $timestamp = $overrideTimestamp ?? (string) ($record['check_in_timestamp'] ?? '');
    $formattedTimestamp = '';
    if (!empty($timestamp)) {
        $time = strtotime($timestamp);
        if ($time !== false) {
            $formattedTimestamp = date('d/m/Y H:i', $time) . ' hs';
        }
    }

    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'full_name' => trim($firstName . ' ' . $lastName),
        'dni_legajo' => (string) ($record['dni_legajo'] ?? ''),
        'branch' => $branch,
        'company' => $company,
        'location' => $location,
        'profile_type' => $profileType,
        'offline_code' => (string) ($record['offline_code'] ?? ''),
        'qr_code_text' => (string) ($record['qr_code_text'] ?? ''),
        'checked_in_at' => $timestamp,
        'checked_in_at_formatted' => $formattedTimestamp,
    ];
}

function composePersonHeadline(array $person): array
{
    $fullName = trim((string) ($person['full_name'] ?? ''));
    if ($fullName === '') {
        $first = trim((string) ($person['first_name'] ?? ''));
        $last = trim((string) ($person['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last);
    }

    if ($fullName === '') {
        $fullName = 'Invitado';
    }

    $dniLegajo = trim((string) ($person['dni_legajo'] ?? ''));

    return [$fullName, $dniLegajo];
}

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
