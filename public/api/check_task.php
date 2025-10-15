<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/dashscope_client.php';

header('Content-Type: application/json');

try {
    $taskId = $_GET['taskId'] ?? '';
    if ($taskId === '') {
        throw new RuntimeException('El taskId es obligatorio.');
    }

    $response = dashscope_get_task($taskId);

    echo json_encode([
        'success' => true,
        'data' => $response,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
