<?php
const STORAGE_DIR = __DIR__ . '/../data';
const CSV_FILE = STORAGE_DIR . '/registrations.csv';
const QR_DIR = __DIR__ . '/../qrcodes';
const SPECIAL_TEST_EMAIL = 'angel.barrios@solodeportes.com';

const QR_REMOTE_SERVICES = [
    'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=%s',
    'https://quickchart.io/qr?size=400&text=%s',
    'https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl=%s&chld=H%%7C1',
];
const CSV_DELIMITER = ',';
const CSV_ENCLOSURE = '"';
const CSV_ESCAPE = '\\';

function getPublicBaseUrl(): string
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $port = $_SERVER['SERVER_PORT'] ?? '';
    $port = is_string($port) ? $port : (string) $port;

    $isDefaultPort = ($scheme === 'https' && $port === '443') || ($scheme === 'http' && $port === '80');
    if ($port !== '' && !$isDefaultPort && strpos($host, ':') === false) {
        $host .= ':' . $port;
    }

    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $relative = '';
    if ($documentRoot !== '') {
        $documentRootReal = realpath($documentRoot);
        $projectRootReal = realpath(__DIR__ . '/..');
        if ($documentRootReal !== false && $projectRootReal !== false && strpos($projectRootReal, $documentRootReal) === 0) {
            $relative = trim(str_replace('\\', '/', substr($projectRootReal, strlen($documentRootReal))), '/');
            if ($relative !== '') {
                $relative .= '/';
            }
        }
    }

    return rtrim($scheme . '://' . $host . '/' . $relative, '/') . '/';
}

function ensureStorage(): void
{
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, 0775, true);
    }
    if (!is_dir(QR_DIR)) {
        mkdir(QR_DIR, 0775, true);
    }
    if (!file_exists(CSV_FILE)) {
        $headers = [
            'timestamp', 'profile_type', 'first_name', 'last_name', 'email', 'phone',
            'dni_legajo', 'branch', 'company', 'offline_code', 'qr_filename',
            'qr_code_text', 'checked_in', 'check_in_timestamp'
        ];
        $fp = fopen(CSV_FILE, 'w');
        fputcsv($fp, $headers, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
        fclose($fp);
    }
}

function readRegistrations(): array
{
    ensureStorage();
    $rows = [];
    if (($fp = fopen(CSV_FILE, 'r')) !== false) {
        $headers = fgetcsv($fp);
        if ($headers === false) {
            fclose($fp);
            return $rows;
        }
        while (($data = fgetcsv($fp)) !== false) {
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), '');
            }
            $rows[] = array_combine($headers, array_slice($data, 0, count($headers)));
        }
        fclose($fp);
    }
    return $rows;
}

function appendRegistration(array $data): void
{
    ensureStorage();
    $fp = fopen(CSV_FILE, 'a');
    if ($fp === false) {
        throw new RuntimeException('No se pudo abrir el archivo de registros.');
    }
    $headers = [];
    if (($headerHandle = fopen(CSV_FILE, 'r')) !== false) {
        $headers = fgetcsv($headerHandle);
        fclose($headerHandle);
    }
    if (!empty($headers) && count($headers) === count($data)) {
        fputcsv($fp, $data, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
    } elseif (!empty($headers)) {
        $ordered = [];
        $assoc = is_array($data) && array_keys($data) !== range(0, count($data) - 1) ? $data : [];
        foreach ($headers as $header) {
            $ordered[] = $assoc[$header] ?? '';
        }
        fputcsv($fp, $ordered, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
    } else {
        fputcsv($fp, $data, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
    }
    fclose($fp);
}

function updateRegistration(string $offlineCode, callable $callback): bool
{
    ensureStorage();
    $rows = [];
    $updated = false;
    if (($fp = fopen(CSV_FILE, 'r')) !== false) {
        $headers = fgetcsv($fp);
        if ($headers === false) {
            fclose($fp);
            return false;
        }
        while (($data = fgetcsv($fp)) !== false) {
            $row = array_combine($headers, $data);
            if ($row['offline_code'] === $offlineCode) {
                $newRow = $callback($row);
                if ($newRow !== null) {
                    $rows[] = $newRow;
                    $updated = true;
                }
            } else {
                $rows[] = $row;
            }
        }
        fclose($fp);

        if ($updated) {
            $fp = fopen(CSV_FILE, 'w');
            fputcsv($fp, $headers, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
            foreach ($rows as $row) {
                $ordered = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    if (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }
                    $ordered[] = $value;
                }
                fputcsv($fp, $ordered, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
            }
            fclose($fp);
        }
    }
    return $updated;
}

function generateOfflineCode(): string
{
    return strtoupper(bin2hex(random_bytes(4))) . '-' . random_int(1000, 9999);
}

function downloadQrImage(string $url): string
{
    $context = stream_context_create([
        'http' => ['timeout' => 10],
        'https' => ['timeout' => 10],
    ]);

    $imageData = @file_get_contents($url, false, $context);
    if ($imageData !== false) {
        return $imageData;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('No se pudo descargar el código QR del servicio remoto (cURL no disponible).');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_USERAGENT => 'GestioQR/1.0',
    ]);

    $imageData = curl_exec($ch);
    if ($imageData === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('No se pudo generar el código QR: ' . ($error ?: 'error desconocido'));
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode >= 400) {
        throw new RuntimeException('No se pudo generar el código QR (HTTP ' . $statusCode . ').');
    }

    return $imageData;
}

function generateQrCode(string $code): string
{
    ensureStorage();

    $encoded = rawurlencode($code);
    $lastException = null;
    $lastServiceUrl = null;

    foreach (QR_REMOTE_SERVICES as $service) {
        $url = sprintf($service, $encoded);
        $lastServiceUrl = $url;

        try {
            $imageData = downloadQrImage($url);
        } catch (RuntimeException $exception) {
            $lastException = $exception;
            continue;
        }

        if ($imageData !== '') {
            $filename = 'qr_' . preg_replace('/[^A-Za-z0-9]/', '', $code) . '_' . time() . '.png';
            $path = QR_DIR . '/' . $filename;

            if (file_put_contents($path, $imageData) === false) {
                throw new RuntimeException('No se pudo guardar el código QR generado.');
            }

            return $filename;
        }
    }

    $message = 'No se pudo generar el código QR.';
    if ($lastException !== null) {
        $message .= ' ' . $lastException->getMessage();
    }
    if ($lastServiceUrl !== null) {
        $message .= ' (Servicio: ' . $lastServiceUrl . ')';
    }

    throw new RuntimeException($message);
}

function formatCheckInTimestamp(?string $timestamp): string
{
    if ($timestamp === null) {
        return '';
    }

    $normalized = trim((string) $timestamp);
    if ($normalized === '') {
        return '';
    }

    $time = strtotime($normalized);
    if ($time === false) {
        return '';
    }

    return date('d/m/Y H:i', $time) . ' hs';
}

function findRegistrationByOfflineCode(string $offlineCode): ?array
{
    $offlineCode = trim($offlineCode);
    if ($offlineCode === '') {
        return null;
    }

    foreach (readRegistrations() as $registration) {
        if (($registration['offline_code'] ?? '') === $offlineCode) {
            return $registration;
        }
    }

    return null;
}

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
    $formattedTimestamp = formatCheckInTimestamp($timestamp);

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

function registrationFieldExists(string $field, string $value, bool $caseSensitive = false): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    $records = readRegistrations();
    foreach ($records as $record) {
        if (!array_key_exists($field, $record)) {
            continue;
        }

        $candidate = (string) $record[$field];
        if ($candidate === '') {
            continue;
        }

        if ($caseSensitive ? ($candidate === $value) : (strcasecmp($candidate, $value) === 0)) {
            return true;
        }
    }

    return false;
}

function registrationExists(string $email): bool
{
    return registrationFieldExists('email', $email);
}

function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

function buildEmailBody(array $context, string $qrPath, string $qrCodeText): string
{
    $templatePath = __DIR__ . '/../templates/email_template.html';
    if (!file_exists($templatePath)) {
        $template = '<h1>Tu invitación está lista</h1><p>Hola {{first_name}},</p><p>Mostrá este código en la entrada:</p><p>{{qr_image_inline}}</p><p><a href="{{qr_download_link}}">Descargar QR</a></p><p>Código de respaldo: <strong>{{offline_code}}</strong></p>';
    } else {
        $template = file_get_contents($templatePath);
    }

    $replacements = [
        '{{first_name}}' => htmlspecialchars($context['first_name']),
        '{{last_name}}' => htmlspecialchars($context['last_name']),
        '{{full_name}}' => htmlspecialchars($context['first_name'] . ' ' . $context['last_name']),
        '{{profile_type}}' => htmlspecialchars(ucfirst($context['profile_type'])),
        '{{branch}}' => htmlspecialchars($context['branch'] ?? ''),
        '{{company}}' => htmlspecialchars($context['company'] ?? ''),
        '{{offline_code}}' => htmlspecialchars($context['offline_code']),
        '{{qr_code_text}}' => htmlspecialchars($qrCodeText),
        '{{phone}}' => htmlspecialchars($context['phone']),
        '{{email}}' => htmlspecialchars($context['email']),
    ];

    $html = strtr($template, $replacements);

    $qrFullPath = QR_DIR . '/' . $qrPath;
    $qrRaw = @file_get_contents($qrFullPath);
    $imgTag = '<p style="margin:0;color:#ef4444;font-size:14px;">No se pudo adjuntar el QR. Descargalo con el botón.</p>';

    if ($qrRaw !== false) {
        $qrData = base64_encode($qrRaw);
        $imgTag = '<img src="data:image/png;base64,' . $qrData . '" alt="Código QR" style="max-width:300px;width:100%;height:auto;border-radius:16px;">';
    }

    $downloadLink = getPublicBaseUrl() . 'qrcodes/' . rawurlencode($qrPath);

    $html = str_replace('{{qr_image_inline}}', $imgTag, $html);
    return str_replace('{{qr_download_link}}', htmlspecialchars($downloadLink, ENT_QUOTES, 'UTF-8'), $html);
}

function sendQrEmail(array $context, string $qrPath, string $qrCodeText): void
{
    $to = $context['email'];
    $subject = '🎉 Entrada evento Fin de Año Solo Deportes 2025 🎟️';
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $headers[] = 'From: Fiesta Exclusiva <no-reply@gestio.local>';

    $body = buildEmailBody($context, $qrPath, $qrCodeText);

    @mail($to, $subject, $body, implode("\r\n", $headers));
}

function createRegistration(array $payload, bool $sendEmail = true): array
{
    $profileType = $payload['profile_type'];
    $firstName = sanitize($payload['first_name'] ?? '');
    $lastName = sanitize($payload['last_name'] ?? '');
    $email = strtolower(trim($payload['email'] ?? ''));
    $phone = sanitize($payload['phone'] ?? '');

    if ($profileType === 'empleado') {
        $dniLegajo = sanitize($payload['dni_legajo'] ?? '');
        $branch = sanitize($payload['branch'] ?? '');
        $company = '';
        if ($branch === 'OTRO (Completar derecha)') {
            $branch = sanitize($payload['other_branch'] ?? 'Otro');
        }
    } elseif ($profileType === 'proveedor') {
        $dniLegajo = sanitize($payload['dni_provider'] ?? '');
        $branch = '';
        $company = sanitize($payload['company'] ?? '');
    } else {
        $dniLegajo = '';
        $branch = sanitize($payload['branch'] ?? '');
        $company = sanitize($payload['company'] ?? ($payload['label'] ?? 'Invitado especial'));
    }

    $offlineCode = generateOfflineCode();
    $qrCodeText = 'EVT-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
    $qrFilename = generateQrCode($qrCodeText);

    $record = [
        'timestamp' => date('Y-m-d H:i:s'),
        'profile_type' => $profileType,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'dni_legajo' => $dniLegajo,
        'branch' => $branch,
        'company' => $company,
        'offline_code' => $offlineCode,
        'qr_filename' => $qrFilename,
        'qr_code_text' => $qrCodeText,
        'checked_in' => '0',
        'check_in_timestamp' => ''
    ];

    appendRegistration(array_values($record));

    if ($sendEmail && !empty($email)) {
        sendQrEmail($record, $qrFilename, $qrCodeText);
    }

    return $record;
}
