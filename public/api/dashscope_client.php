<?php
/**
 * Simple DashScope International client wrapper for shared hosting.
 * Update DASH_SCOPE_API_KEY in the generated config.php or via environment variable.
 */

const DASH_SCOPE_API_ENDPOINT = 'https://dashscope-intl.aliyuncs.com/api/v1';
const DASH_SCOPE_API_KEY_ENV_NAMES = ['DASH_SCOPE_API_KEY', 'DASHSCOPE_API_KEY'];

// Models
const DASH_SCOPE_MODEL_T2V = 'wanx-v1-t2v';
const DASH_SCOPE_MODEL_I2V = 'wanx-v1-i2v';
const DASH_SCOPE_MODEL_CHAT = 'qwen-max';

/**
 * Load API key from config or environment.
 * Shared hosting friendly: use config.php to set constant.
 */
function dashscope_get_api_key(): string
{
    if (defined('DASH_SCOPE_API_KEY') && DASH_SCOPE_API_KEY) {
        return DASH_SCOPE_API_KEY;
    }

    foreach (DASH_SCOPE_API_KEY_ENV_NAMES as $envName) {
        $envValue = getenv($envName);
        if ($envValue) {
            return $envValue;
        }
    }

    throw new RuntimeException('DashScope API key is not configured. Define DASH_SCOPE_API_KEY in api/config.php or set the DASH_SCOPE_API_KEY/DASHSCOPE_API_KEY environment variable.');
}

/**
 * Perform HTTP request to DashScope.
 */
function dashscope_request(string $method, string $path, ?array $body = null, array $headers = []): array
{
    $url = rtrim(DASH_SCOPE_API_ENDPOINT, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $defaultHeaders = [
        'Authorization: Bearer ' . dashscope_get_api_key(),
        'Content-Type: application/json'
    ];

    $payload = null;
    if ($body !== null) {
        $payload = json_encode($body);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode request body.');
        }
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_TIMEOUT => 120,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('CURL error: ' . $err);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Failed to decode API response: ' . json_last_error_msg());
    }

    if ($statusCode >= 400) {
        $message = $decoded['message'] ?? ('HTTP ' . $statusCode);
        throw new RuntimeException('DashScope API error: ' . $message, $statusCode);
    }

    return $decoded;
}

/**
 * Start an asynchronous video generation task.
 *
 * @param string $mode Either 't2v' or 'i2v'
 * @param array $payload Additional payload keys depending on the mode
 * @return array
 */
function dashscope_start_video_task(string $mode, array $payload): array
{
    $model = $mode === 'i2v' ? DASH_SCOPE_MODEL_I2V : DASH_SCOPE_MODEL_T2V;

    $body = [
        'model' => $model,
        'input' => $payload,
        'parameters' => [
            'mode' => $mode
        ]
    ];

    return dashscope_request('POST', 'videos', $body);
}

/**
 * Retrieve asynchronous task status.
 */
function dashscope_get_task(string $taskId): array
{
    return dashscope_request('GET', 'videos/' . urlencode($taskId));
}

/**
 * Execute a synchronous chat completion request using Qwen.
 */
function dashscope_chat(string $prompt, ?string $systemPrompt = null): array
{
    $body = [
        'model' => DASH_SCOPE_MODEL_CHAT,
        'input' => [
            'messages' => array_values(array_filter([
                $systemPrompt ? ['role' => 'system', 'content' => $systemPrompt] : null,
                ['role' => 'user', 'content' => $prompt]
            ]))
        ]
    ];

    return dashscope_request('POST', 'chat/completions', $body);
}
