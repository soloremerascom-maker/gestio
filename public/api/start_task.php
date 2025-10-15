<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/dashscope_client.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $mode = $_POST['mode'] ?? '';
    if (!in_array($mode, ['t2v', 'i2v'], true)) {
        throw new RuntimeException('Modo inválido.');
    }

    $prompt = trim($_POST['prompt'] ?? '');
    if ($prompt === '') {
        throw new RuntimeException('El prompt es obligatorio.');
    }

    $format = $_POST['format'] ?? 'youtube';
    $formatMap = [
        'youtube' => ['aspect_ratio' => '16:9', 'resolution' => '1920x1080'],
        'tiktok' => ['aspect_ratio' => '9:16', 'resolution' => '1080x1920'],
        'square' => ['aspect_ratio' => '1:1', 'resolution' => '1080x1080'],
    ];

    $videoConfig = $formatMap[$format] ?? $formatMap['youtube'];

    $payload = [
        'prompt' => $prompt,
        'video' => [
            'aspect_ratio' => $videoConfig['aspect_ratio'],
            'resolution' => $videoConfig['resolution'],
        ],
    ];

    if ($mode === 'i2v') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Debes subir una imagen válida.');
        }
        $tmpPath = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpPath) ?: 'image/png';
        $content = base64_encode(file_get_contents($tmpPath));
        $payload['image'] = [
            'filename' => $_FILES['image']['name'],
            'content' => 'data:' . $mimeType . ';base64,' . $content,
        ];
    }

    if (!empty($_POST['negative_prompt'])) {
        $payload['negative_prompt'] = trim($_POST['negative_prompt']);
    }

    $response = dashscope_start_video_task($mode, $payload);

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
