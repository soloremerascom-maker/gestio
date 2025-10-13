<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$action = isset($payload['action']) ? trim((string) $payload['action']) : '';
$offlineCode = isset($payload['offline_code']) ? trim((string) $payload['offline_code']) : '';

if ($offlineCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Código de invitación faltante']);
    exit;
}

$existing = findRegistrationByOfflineCode($offlineCode);
if (!$existing) {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró el registro solicitado']);
    exit;
}

$action = strtolower($action);

switch ($action) {
    case 'check-in':
    case 'check_in':
    case 'agregar':
        if (($existing['checked_in'] ?? '') === '1') {
            echo json_encode([
                'status' => 'ok',
                'message' => 'El invitado ya figuraba como ingresado.',
                'registration' => $existing,
                'formatted_timestamp' => formatCheckInTimestamp($existing['check_in_timestamp'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $updatedRow = $existing;
        $success = updateRegistration($offlineCode, function ($row) use ($now, &$updatedRow) {
            $row['checked_in'] = '1';
            $row['check_in_timestamp'] = $now;
            $updatedRow = $row;
            return $row;
        });

        if (!$success) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el estado del invitado']);
            exit;
        }

        $person = buildPersonPayload($updatedRow, $now);
        [$displayName, $identifier] = composePersonHeadline($person);
        $label = $identifier !== '' ? ' (' . $identifier . ')' : '';

        echo json_encode([
            'status' => 'ok',
            'message' => 'Ingreso agregado para ' . $displayName . $label . '.',
            'registration' => $updatedRow,
            'formatted_timestamp' => formatCheckInTimestamp($now),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'clear':
    case 'limpiar':
    case 'clear_check_in':
        if (($existing['checked_in'] ?? '') !== '1') {
            echo json_encode([
                'status' => 'ok',
                'message' => 'El invitado ya figuraba como pendiente.',
                'registration' => $existing,
                'formatted_timestamp' => '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $updatedRow = $existing;
        $success = updateRegistration($offlineCode, function ($row) use (&$updatedRow) {
            $row['checked_in'] = '0';
            $row['check_in_timestamp'] = '';
            $updatedRow = $row;
            return $row;
        });

        if (!$success) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo restablecer el estado del invitado']);
            exit;
        }

        echo json_encode([
            'status' => 'ok',
            'message' => 'Ingreso limpiado correctamente.',
            'registration' => $updatedRow,
            'formatted_timestamp' => '',
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'delete':
    case 'eliminar':
        $deletedRow = $existing;
        $success = updateRegistration($offlineCode, function ($row) use (&$deletedRow) {
            $deletedRow = $row;
            return null; // omitir del nuevo archivo
        });

        if (!$success) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el registro']);
            exit;
        }

        echo json_encode([
            'status' => 'ok',
            'message' => 'Invitado eliminado correctamente.',
            'registration' => $deletedRow,
            'formatted_timestamp' => '',
            'deleted' => true,
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
}
