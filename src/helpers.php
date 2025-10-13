<?php
const STORAGE_DIR = __DIR__ . '/../data';
const CSV_FILE = STORAGE_DIR . '/registrations.csv';
const QR_DIR = __DIR__ . '/../qrcodes';
const SPECIAL_TEST_EMAIL = 'angel.barrios@solodeportes.com';

const QR_REMOTE_SERVICE = 'https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl=%s&chld=H|1';

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
        fputcsv($fp, $headers);
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
        fputcsv($fp, $data);
    } elseif (!empty($headers)) {
        $ordered = [];
        $assoc = is_array($data) && array_keys($data) !== range(0, count($data) - 1) ? $data : [];
        foreach ($headers as $header) {
            $ordered[] = $assoc[$header] ?? '';
        }
        fputcsv($fp, $ordered);
    } else {
        fputcsv($fp, $data);
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
            fputcsv($fp, $headers);
            foreach ($rows as $row) {
                $ordered = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    if (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }
                    $ordered[] = $value;
                }
                fputcsv($fp, $ordered);
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

    $encoded = urlencode($code);
    $url = sprintf(QR_REMOTE_SERVICE, $encoded);

    $imageData = downloadQrImage($url);
    if ($imageData === '') {
        throw new RuntimeException('No se pudo generar el código QR.');
    }

    $filename = 'qr_' . preg_replace('/[^A-Za-z0-9]/', '', $code) . '_' . time() . '.png';
    $path = QR_DIR . '/' . $filename;

    if (file_put_contents($path, $imageData) === false) {
        throw new RuntimeException('No se pudo guardar el código QR generado.');
    }

    return $filename;
}

function registrationExists(string $email): bool
{
    if ($email === '') {
        return false;
    }
    $records = readRegistrations();
    foreach ($records as $record) {
        if (strcasecmp($record['email'], $email) === 0) {
            return true;
        }
    }
    return false;
}

function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

function buildEmailBody(array $context, string $qrPath, string $qrCodeText): string
{
    $templatePath = __DIR__ . '/../templates/email_template.html';
    if (!file_exists($templatePath)) {
        $template = '<h1>Tu invitación está lista</h1><p>Hola {{first_name}},</p><p>Mostrá este código en la entrada:</p><p><img src="cid:qrImage" alt="Código QR"></p><p>Código de respaldo: <strong>{{offline_code}}</strong></p>';
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
    $qrData = base64_encode(file_get_contents($qrFullPath));
    $imgTag = '<img src="data:image/png;base64,' . $qrData . '" alt="Código QR" style="max-width:300px;width:100%;height:auto;border-radius:16px;">';

    return str_replace('{{qr_image_inline}}', $imgTag, $html);
}

function sendQrEmail(array $context, string $qrPath, string $qrCodeText): void
{
    $to = $context['email'];
    $subject = 'Tu acceso a Fiesta Exclusiva 2025';
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
