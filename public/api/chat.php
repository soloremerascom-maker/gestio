<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/dashscope_client.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido.');
    }

    $message = trim($data['message'] ?? '');
    if ($message === '') {
        throw new RuntimeException('El mensaje es obligatorio.');
    }

    $systemPrompt = isset($data['system']) ? trim((string)$data['system']) : null;

    $response = dashscope_chat($message, $systemPrompt);

    $choices = $response['output']['choices'] ?? [];
    $assistantText = '';
    if ($choices) {
        $assistantMessage = $choices[0]['message']['content'] ?? '';
        if (is_array($assistantMessage)) {
            $assistantText = implode("\n", array_column($assistantMessage, 'text'));
        } else {
            $assistantText = (string)$assistantMessage;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $assistantText,
        'raw' => $response,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
