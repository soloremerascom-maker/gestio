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
    echo json_encode(['status' => 'error', 'message' => 'Código no encontrado']);
    exit;
}

if ($matched['checked_in'] === '1') {
    echo json_encode(['status' => 'error', 'message' => 'Este invitado ya ingresó']);
    exit;
}

$success = updateRegistration($matched['offline_code'], function ($row) {
    $row['checked_in'] = '1';
    $row['check_in_timestamp'] = date('Y-m-d H:i:s');
    return $row;
});

if ($success) {
    $name = trim($matched['first_name'] . ' ' . $matched['last_name']);
    echo json_encode(['status' => 'ok', 'message' => 'Acceso habilitado para ' . $name]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el registro']);
}
