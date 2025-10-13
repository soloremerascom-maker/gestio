<?php
session_start();
require_once __DIR__ . '/../src/helpers.php';

const ADMIN_PASSWORD = 'Fiestasd2025*1';

ensureStorage();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    $password = $_POST['admin_password'];
    if (hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
    } else {
        $loginError = 'Contraseña incorrecta';
    }
}

if (!($_SESSION['is_admin'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Administrador</title>
        <link rel="stylesheet" href="../assets/styles.css">
    </head>
    <body>
        <main>
            <div class="card">
                <h1>Panel del evento</h1>
                <p class="lead">Ingresá con la contraseña segura para gestionar a los invitados.</p>
                <?php if (!empty($loginError)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="input-group">
                        <label for="admin_password">Contraseña</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                    <button class="button" type="submit">Ingresar</button>
                </form>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$registrations = readRegistrations();
$total = count($registrations);
$employees = count(array_filter($registrations, fn($r) => $r['profile_type'] === 'empleado'));
$providers = count(array_filter($registrations, fn($r) => $r['profile_type'] === 'proveedor'));
$specials = $total - $employees - $providers;
$checkedIn = count(array_filter($registrations, fn($r) => $r['checked_in'] === '1'));

$tab = $_GET['tab'] ?? 'dashboard';
$feedback = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$filteredRegistrations = $registrations;

if ($tab === 'list' && $searchQuery !== '') {
    $filteredRegistrations = array_values(array_filter($registrations, function ($row) use ($searchQuery) {
        $haystacks = [
            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['email'] ?? '',
            $row['dni_legajo'] ?? '',
            $row['branch'] ?? '',
            $row['company'] ?? '',
            $row['offline_code'] ?? '',
            $row['qr_code_text'] ?? '',
        ];

        foreach ($haystacks as $value) {
            if ($value !== '' && stripos($value, $searchQuery) !== false) {
                return true;
            }
        }

        return false;
    }));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración - Fiesta Exclusiva</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .admin-header a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
        }
        .admin-section {
            margin-top: 2rem;
        }
        textarea {
            min-height: 160px;
            resize: vertical;
        }
        .tab-bar {
            flex-wrap: wrap;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            -webkit-overflow-scrolling: touch;
        }
        .tab-button {
            flex: 1 0 auto;
            text-align: center;
        }
        .scan-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 1050;
        }
        .scan-modal-backdrop.hidden {
            display: none;
        }
        .scan-modal {
            background: #fff;
            border-radius: 20px;
            padding: 1.75rem;
            width: min(420px, 100%);
            text-align: center;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
        }
        .scan-modal h3 {
            margin: 0 0 0.75rem;
            font-size: 1.35rem;
        }
        .scan-modal-body {
            color: var(--muted);
            line-height: 1.6;
        }
        .scan-modal.success h3 {
            color: #047857;
        }
        .scan-modal.error h3 {
            color: #b91c1c;
        }
        .scan-modal-body p {
            margin: 0 0 1rem;
        }
        .scan-details {
            margin-top: 1rem;
            background: rgba(148, 163, 184, 0.12);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            text-align: left;
        }
        .scan-details dt {
            font-weight: 600;
            color: var(--text);
        }
        .scan-details dd {
            margin: 0 0 0.75rem;
            color: var(--muted);
        }
        .scan-details dd strong {
            color: var(--text);
            font-weight: 600;
        }
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }
        .search-form .input-group {
            flex: 1 1 260px;
            margin: 0;
        }
        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .search-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.1rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.18);
            color: var(--muted);
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .search-clear:hover {
            background: rgba(148, 163, 184, 0.28);
            color: var(--text);
        }
        .admin-table {
            width: 100%;
        }
        .admin-table thead th,
        .admin-table tbody td {
            font-size: 0.9rem;
        }
        .admin-table tbody td {
            vertical-align: top;
        }
        .cell-primary {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .cell-primary .cell-subtitle {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .cell-secondary,
        .cell-id,
        .cell-origin,
        .cell-code {
            font-size: 0.85rem;
            color: var(--muted);
            white-space: nowrap;
        }
        .cell-code {
            font-family: 'Fira Mono', 'SFMono-Regular', Menlo, monospace;
            font-size: 0.8rem;
        }
        .cell-ellipsis {
            display: inline-block;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cell-status {
            min-width: 120px;
        }
        .cell-status .cell-meta {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.75rem;
            color: var(--muted);
        }
        .cell-actions {
            white-space: nowrap;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .action-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }
        .action-chip:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .action-chip--success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border-color: rgba(16, 185, 129, 0.45);
        }
        .action-chip--muted {
            background: rgba(148, 163, 184, 0.18);
            color: var(--muted);
            border-color: rgba(148, 163, 184, 0.28);
        }
        .action-chip--danger {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .action-chip:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }
        .badge--success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        .badge--danger {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }
        .badge--muted {
            background: rgba(148, 163, 184, 0.18);
            color: var(--muted);
        }
        .list-feedback {
            display: none;
            margin-bottom: 1rem;
        }
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
        }
        @media (max-width: 600px) {
            .admin-section h2 {
                font-size: 1.45rem;
            }
            .tab-bar {
                gap: 0.5rem;
            }
            .tab-button {
                padding: 0.65rem 1rem;
                font-size: 0.95rem;
            }
            .search-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .search-actions .button,
            .search-clear {
                flex: 1 1 auto;
                justify-content: center;
            }
        }
        @media (max-width: 720px) {
            .table-wrapper {
                padding: 0;
                background: transparent;
                border: none;
            }
            .admin-table thead {
                display: none;
            }
            .admin-table tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
                padding: 1rem 1.1rem;
                margin-bottom: 1rem;
                border-radius: 16px;
                border: 1px solid rgba(148, 163, 184, 0.25);
                background: rgba(255, 255, 255, 0.95);
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
            }
            .admin-table tbody td {
                border-top: none;
                padding: 0;
                white-space: normal;
            }
            .admin-table tbody td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.7rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--muted);
                margin-bottom: 0.2rem;
            }
            .admin-table tbody td.cell-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
                padding-top: 0.25rem;
            }
            .cell-primary,
            .cell-secondary,
            .cell-id,
            .cell-origin,
            .cell-code {
                font-size: 0.95rem;
            }
            .cell-status {
                min-width: unset;
            }
            .cell-status .cell-meta {
                margin-top: 0.15rem;
            }
            .action-chip {
                flex: 1 1 calc(50% - 0.5rem);
                justify-content: center;
            }
        }
    </style>
    <script>
        const QR_SCAN_ENDPOINT = 'scan.php';
        const REGISTRATION_MANAGE_ENDPOINT = 'manage_registration.php';
    </script>
</head>
<body>
    <main>
        <div class="card">
            <div class="admin-header">
                <div>
                    <h1>Panel de control</h1>
                    <p class="lead">Gestioná accesos, envíos y el control de ingreso en tiempo real.</p>
                </div>
                <a href="?logout=1">Cerrar sesión</a>
            </div>

            <?php if ($feedback): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($feedback); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Invitados totales</h3>
                    <p><?php echo $total; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Empleados</h3>
                    <p><?php echo $employees; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Proveedores</h3>
                    <p><?php echo $providers; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Especiales</h3>
                    <p><?php echo $specials; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Ingresados</h3>
                    <p><?php echo $checkedIn; ?></p>
                </div>
            </div>

            <div class="tab-bar admin-section">
                <a class="tab-button <?php echo $tab === 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">Control de ingreso</a>
                <a class="tab-button <?php echo $tab === 'bulk-email' ? 'active' : ''; ?>" href="?tab=bulk-email">Envío masivo</a>
                <a class="tab-button <?php echo $tab === 'bulk-qr' ? 'active' : ''; ?>" href="?tab=bulk-qr">Generador manual</a>
                <a class="tab-button <?php echo $tab === 'list' ? 'active' : ''; ?>" href="?tab=list">Listado completo</a>
            </div>

            <?php if ($tab === 'dashboard'): ?>
                <section class="admin-section">
                    <h2>Escaneo de códigos</h2>
                    <p class="lead">Usá la cámara para validar invitaciones o ingresá el código manualmente.</p>
                    <div id="qr-reader" style="width:100%;max-width:420px;margin-bottom:1.5rem;"></div>
                    <div class="grid">
                        <div class="input-group">
                            <label for="manual_code">Ingresar código manual</label>
                            <input type="text" id="manual_code" placeholder="Código de respaldo o QR">
                        </div>
                        <button class="button" id="manual_check_button" type="button">Validar código</button>
                    </div>
                    <div id="scan_result" class="alert" style="margin-top:1.5rem;display:none;"></div>
                    <div id="scan_modal" class="scan-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="scan_modal_title">
                        <div class="scan-modal" id="scan_modal_card">
                            <h3 id="scan_modal_title">Resultado del escaneo</h3>
                            <div id="scan_modal_message" class="scan-modal-body">
                                <p>Mostraremos el detalle apenas se valide el código.</p>
                            </div>
                            <button class="button" type="button" id="scan_modal_close">Aceptar</button>
                        </div>
                    </div>
                </section>
            <?php elseif ($tab === 'bulk-email'): ?>
                <section class="admin-section">
                    <h2>Envío masivo (hasta 20)</h2>
                    <p class="lead">Pegá una lista de correos (uno por línea). Se generará un QR y recibirán un correo automático.</p>
                    <form method="post" action="send_bulk.php">
                        <textarea name="emails" placeholder="correo1@dominio.com&#10;correo2@dominio.com" required></textarea>
                        <div class="input-group">
                            <label for="bulk_label">Etiqueta interna (opcional)</label>
                            <input type="text" id="bulk_label" name="label" placeholder="Invitados VIP, Staff, etc.">
                        </div>
                        <button class="button" type="submit">Enviar invitaciones</button>
                    </form>
                </section>
            <?php elseif ($tab === 'bulk-qr'): ?>
                <section class="admin-section">
                    <h2>Generar QR sin correo</h2>
                    <p class="lead">Creá códigos para invitados especiales y descargalos como imagen.</p>
                    <form method="post" action="generate_qr.php">
                        <div class="input-group">
                            <label for="quantity">Cantidad de QR</label>
                            <input type="number" id="quantity" name="quantity" min="1" max="50" value="5" required>
                        </div>
                        <div class="input-group">
                            <label for="notes">Notas internas</label>
                            <input type="text" id="notes" name="notes" placeholder="Ej: Invitados protocolo">
                        </div>
                        <button class="button" type="submit">Generar ahora</button>
                    </form>

                    <?php if (!empty($_SESSION['generated_qr_batch'])): ?>
                        <div class="admin-section">
                            <h3>Última generación</h3>
                            <div class="qr-grid">
                                <?php foreach ($_SESSION['generated_qr_batch'] as $item): ?>
                                    <div class="qr-card">
                                        <img src="../qrcodes/<?php echo htmlspecialchars($item['qr_filename']); ?>" alt="QR generado">
                                        <p><strong><?php echo htmlspecialchars($item['offline_code']); ?></strong></p>
                                        <small><?php echo htmlspecialchars($item['qr_code_text']); ?></small>
                                        <div style="margin-top:0.75rem;">
                                            <a class="tab-button" href="../qrcodes/<?php echo htmlspecialchars($item['qr_filename']); ?>" download>Descargar</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($tab === 'list'): ?>
                <section class="admin-section">
                    <h2>Listado de asistentes</h2>
                    <form class="search-form" method="get">
                        <input type="hidden" name="tab" value="list">
                        <div class="input-group">
                            <label for="search_query">Buscar invitado</label>
                            <input type="search" id="search_query" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Nombre, correo, DNI, legajo o código">
                        </div>
                        <div class="search-actions">
                            <button class="button" type="submit">Buscar</button>
                            <?php if ($searchQuery !== ''): ?>
                                <a class="search-clear" href="?tab=list">Limpiar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div id="list_feedback" class="alert list-feedback"></div>
                    <div class="table-wrapper">
                        <table class="admin-table" data-role="registrations-table">
                            <thead>
                                <tr>
                                    <th>Invitado</th>
                                    <th>Correo</th>
                                    <th>DNI / Legajo</th>
                                    <th>Origen</th>
                                    <th>Ingreso</th>
                                    <th>Código</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-role="registrations-body">
                                <?php $hasRows = !empty($filteredRegistrations); ?>
                                <tr data-role="empty-state" class="<?php echo $hasRows ? 'hidden' : ''; ?>">
                                    <td colspan="7" style="text-align:center;color:var(--muted);padding:1.5rem;">No se encontraron coincidencias.</td>
                                </tr>
                                <?php if ($hasRows): ?>
                                    <?php foreach ($filteredRegistrations as $row): ?>
                                        <?php
                                            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                            $displayName = $fullName !== '' ? $fullName : 'Invitado';
                                            $profileBadge = $row['profile_type'] ?: 'especial';
                                            $identifier = $row['dni_legajo'] ?? '';
                                            $origin = '';
                                            if (($row['profile_type'] ?? '') === 'empleado') {
                                                $origin = $row['branch'] ?? '';
                                            } elseif (($row['profile_type'] ?? '') === 'proveedor') {
                                                $origin = $row['company'] ?? '';
                                            } else {
                                                $origin = 'Invitado especial';
                                            }
                                            $isChecked = ($row['checked_in'] ?? '') === '1';
                                            $formattedTime = formatCheckInTimestamp($row['check_in_timestamp'] ?? '');
                                        ?>
                                        <tr
                                            data-offline-code="<?php echo htmlspecialchars($row['offline_code']); ?>"
                                            data-name="<?php echo htmlspecialchars($displayName); ?>"
                                            data-checked-in="<?php echo $isChecked ? '1' : '0'; ?>"
                                        >
                                            <td class="cell-primary" data-label="Invitado">
                                                <span class="cell-ellipsis" title="<?php echo htmlspecialchars($displayName); ?>"><?php echo htmlspecialchars($displayName); ?></span>
                                                <span class="cell-subtitle"><span class="badge badge--muted"><?php echo htmlspecialchars(ucfirst($profileBadge)); ?></span></span>
                                            </td>
                                            <td class="cell-secondary" data-label="Correo">
                                                <span class="cell-ellipsis" title="<?php echo htmlspecialchars($row['email']); ?>"><?php echo htmlspecialchars($row['email']); ?></span>
                                            </td>
                                            <td class="cell-id" data-label="DNI / Legajo">
                                                <span class="cell-ellipsis" title="<?php echo htmlspecialchars($identifier); ?>"><?php echo htmlspecialchars($identifier); ?></span>
                                            </td>
                                            <td class="cell-origin" data-label="Origen">
                                                <span class="cell-ellipsis" title="<?php echo htmlspecialchars($origin); ?>"><?php echo htmlspecialchars($origin); ?></span>
                                            </td>
                                            <td class="cell-status" data-label="Ingreso">
                                                <span class="badge <?php echo $isChecked ? 'badge--success' : 'badge--danger'; ?>" data-role="status-badge"><?php echo $isChecked ? 'Ingresó' : 'Pendiente'; ?></span>
                                                <small class="cell-meta" data-role="status-meta"><?php echo htmlspecialchars($formattedTime); ?></small>
                                            </td>
                                            <td class="cell-code" data-label="Código">
                                                <span title="<?php echo htmlspecialchars($row['offline_code']); ?>"><?php echo htmlspecialchars($row['offline_code']); ?></span>
                                            </td>
                                            <td class="cell-actions" data-label="Acciones">
                                                <button class="action-chip action-chip--success" type="button" data-action="check-in" <?php echo $isChecked ? 'disabled' : ''; ?>>Agregar</button>
                                                <button class="action-chip action-chip--muted" type="button" data-action="clear" <?php echo $isChecked ? '' : 'disabled'; ?>>Limpiar</button>
                                                <button class="action-chip action-chip--danger" type="button" data-action="delete">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://unpkg.com/html5-qrcode" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resultBox = document.getElementById('scan_result');
            const manualInput = document.getElementById('manual_code');
            const manualButton = document.getElementById('manual_check_button');
            const modal = document.getElementById('scan_modal');
            const modalCard = document.getElementById('scan_modal_card');
            const modalTitle = document.getElementById('scan_modal_title');
            const modalMessage = document.getElementById('scan_modal_message');
            const modalClose = document.getElementById('scan_modal_close');
            let html5QrCodeInstance = null;
            let modalVisible = false;
            const registrationsBody = document.querySelector('[data-role="registrations-body"]');
            const emptyStateRow = registrationsBody ? registrationsBody.querySelector('[data-role="empty-state"]') : null;
            const listFeedback = document.getElementById('list_feedback');
            let listFeedbackTimer = null;

            function showListFeedback(message, success = true) {
                if (!listFeedback) {
                    return;
                }
                listFeedback.textContent = message ?? '';
                listFeedback.style.display = 'block';
                listFeedback.classList.remove('alert-success', 'alert-error');
                listFeedback.classList.add(success ? 'alert-success' : 'alert-error');

                if (listFeedbackTimer) {
                    clearTimeout(listFeedbackTimer);
                }
                listFeedbackTimer = window.setTimeout(() => {
                    if (listFeedback) {
                        listFeedback.style.display = 'none';
                    }
                }, 5000);
            }

            function updateEmptyState() {
                if (!registrationsBody || !emptyStateRow) {
                    return;
                }
                const hasRows = registrationsBody.querySelectorAll('tr[data-offline-code]').length > 0;
                if (hasRows) {
                    emptyStateRow.classList.add('hidden');
                } else {
                    emptyStateRow.classList.remove('hidden');
                }
            }

            function applyListRowUpdate(row, registration, formattedTimestamp) {
                if (!row || !registration) {
                    return;
                }

                const checkedValue = registration.checked_in ?? registration.checkedIn ?? registration['checked-in'];
                const isChecked = String(checkedValue ?? '').trim() === '1';
                row.dataset.checkedIn = isChecked ? '1' : '0';

                const badge = row.querySelector('[data-role="status-badge"]');
                if (badge) {
                    badge.textContent = isChecked ? 'Ingresó' : 'Pendiente';
                    badge.classList.remove('badge--success', 'badge--danger');
                    badge.classList.add(isChecked ? 'badge--success' : 'badge--danger');
                }

                const meta = row.querySelector('[data-role="status-meta"]');
                if (meta) {
                    meta.textContent = formattedTimestamp || '';
                }

                const checkButton = row.querySelector('[data-action="check-in"]');
                const clearButton = row.querySelector('[data-action="clear"]');
                const deleteButton = row.querySelector('[data-action="delete"]');

                if (checkButton) {
                    checkButton.disabled = isChecked;
                }
                if (clearButton) {
                    clearButton.disabled = !isChecked;
                }
                if (deleteButton) {
                    deleteButton.disabled = false;
                }

                const registrationName = [registration.first_name, registration.last_name]
                    .map((value) => (value ?? '').toString().trim())
                    .filter(Boolean)
                    .join(' ')
                    .trim();
                const safeName = registrationName || 'Invitado';
                row.dataset.name = safeName;
                const nameCell = row.querySelector('.cell-primary .cell-ellipsis');
                if (nameCell) {
                    nameCell.textContent = safeName;
                    nameCell.setAttribute('title', safeName);
                }

                const email = (registration.email ?? '').toString().trim();
                if (email) {
                    const emailCell = row.querySelector('.cell-secondary .cell-ellipsis');
                    if (emailCell) {
                        emailCell.textContent = email;
                        emailCell.setAttribute('title', email);
                    }
                }

                const identifier = (registration.dni_legajo ?? '').toString().trim();
                const idCell = row.querySelector('.cell-id .cell-ellipsis');
                if (idCell) {
                    idCell.textContent = identifier;
                    idCell.setAttribute('title', identifier);
                }

                const profileType = (registration.profile_type ?? '').toString().trim();
                const branch = (registration.branch ?? '').toString().trim();
                const company = (registration.company ?? '').toString().trim();
                const originValue = profileType === 'empleado' ? branch : (profileType === 'proveedor' ? company : (branch || company || 'Invitado especial'));
                const originCell = row.querySelector('.cell-origin .cell-ellipsis');
                if (originCell) {
                    originCell.textContent = originValue;
                    originCell.setAttribute('title', originValue);
                }

                const badgeLabel = row.querySelector('.cell-primary .badge');
                if (badgeLabel) {
                    const formattedProfile = profileType ? profileType.charAt(0).toUpperCase() + profileType.slice(1) : badgeLabel.textContent;
                    badgeLabel.textContent = formattedProfile;
                }
            }

            async function manageRegistrationAction(action, offlineCode) {
                const response = await fetch(REGISTRATION_MANAGE_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ action, offline_code: offlineCode })
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }

                if (!response.ok || !data) {
                    throw new Error(data?.message ?? 'No se pudo completar la acción.');
                }

                if ((data.status ?? 'error') !== 'ok') {
                    throw new Error(data.message ?? 'No se pudo completar la acción.');
                }

                return data;
            }

            registrationsBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-action]');
                if (!button) {
                    return;
                }

                const action = button.getAttribute('data-action');
                if (!action) {
                    return;
                }

                const row = button.closest('tr[data-offline-code]');
                if (!row) {
                    return;
                }

                const offlineCode = row.getAttribute('data-offline-code');
                if (!offlineCode) {
                    return;
                }

                if (action === 'delete') {
                    const targetName = row.dataset.name || 'el invitado';
                    const confirmed = window.confirm(`¿Eliminar a ${targetName}? Esta acción no se puede deshacer.`);
                    if (!confirmed) {
                        return;
                    }
                }

                const actionButtons = Array.from(row.querySelectorAll('.action-chip'));
                const previousStates = actionButtons.map((btn) => btn.disabled);
                actionButtons.forEach((btn) => {
                    btn.disabled = true;
                });

                try {
                    const result = await manageRegistrationAction(action, offlineCode);
                    const message = result?.message ?? 'Acción completada correctamente.';
                    showListFeedback(message, true);

                    if (result?.deleted) {
                        row.remove();
                        updateEmptyState();
                        return;
                    }

                    applyListRowUpdate(row, result?.registration ?? {}, result?.formatted_timestamp ?? '');
                    updateEmptyState();
                } catch (error) {
                    console.error(error);
                    const fallbackMessage = error instanceof Error ? (error.message || 'No se pudo completar la acción.') : 'No se pudo completar la acción.';
                    showListFeedback(fallbackMessage, false);
                    actionButtons.forEach((btn, index) => {
                        btn.disabled = previousStates[index];
                    });
                }
            });

            function escapeHtml(value) {
                if (value === null || value === undefined) {
                    return '';
                }
                return String(value).replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                })[char] || char);
            }

            function buildDetails(person, status) {
                if (!person || typeof person !== 'object') {
                    return '';
                }

                const rows = [];
                const fullName = (person.full_name || [person.first_name, person.last_name].filter(Boolean).join(' ')).trim();
                rows.push(['Nombre completo', fullName || 'No informado']);

                const dniLegajo = person.dni_legajo ? String(person.dni_legajo).trim() : '';
                rows.push(['DNI / Legajo', dniLegajo || 'No informado']);

                const location = person.location ? String(person.location).trim() : '';
                rows.push(['Sucursal / Área', location || 'No informado']);

                const formattedTimestamp = person.checked_in_at_formatted || person.checked_in_at;
                if (formattedTimestamp && status === 'used') {
                    rows.push(['Utilizado', formattedTimestamp]);
                } else if (formattedTimestamp && status === 'ok') {
                    rows.push(['Ingreso registrado', formattedTimestamp]);
                }

                if (!rows.length) {
                    return '';
                }

                return '<dl class="scan-details">' + rows.map(([label, value]) => '<dt>' + escapeHtml(label) + '</dt><dd><strong>' + escapeHtml(value) + '</strong></dd>').join('') + '</dl>';
            }

            function formatPersonSummary(person) {
                if (!person || typeof person !== 'object') {
                    return '';
                }

                const fullName = (person.full_name || [person.first_name, person.last_name].filter(Boolean).join(' ')).trim();
                const identifierRaw = person.dni_legajo ?? '';
                const identifier = typeof identifierRaw === 'string'
                    ? identifierRaw.trim()
                    : String(identifierRaw ?? '').trim();

                let summary = fullName || 'Invitado';
                if (identifier) {
                    summary += ` (DNI/Legajo: ${identifier})`;
                }

                return summary.trim();
            }

            function showResult(message, success = true, detailsHtml = '') {
                if (!resultBox) return;
                const safeMessage = escapeHtml(message ?? '');
                let html = '<p style="margin:0 0 0.5rem;">' + safeMessage + '</p>';
                if (detailsHtml) {
                    html += detailsHtml;
                }
                resultBox.innerHTML = html;
                resultBox.classList.toggle('alert-error', !success);
                resultBox.classList.toggle('alert-success', success);
                resultBox.style.display = 'block';
            }

            function openModal(title, message, tone = 'success', detailsHtml = '') {
                if (!modal || !modalCard) {
                    showResult(message, tone === 'success', detailsHtml);
                    return;
                }
                modalTitle.textContent = title;
                const safeMessage = escapeHtml(message ?? '');
                let html = '<p>' + safeMessage + '</p>';
                if (detailsHtml) {
                    html += detailsHtml;
                }
                modalMessage.innerHTML = html;
                modalCard.classList.remove('success', 'error');
                modalCard.classList.add(tone === 'success' ? 'success' : 'error');
                modal.classList.remove('hidden');
                modalVisible = true;
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.add('hidden');
                modalVisible = false;
                if (html5QrCodeInstance && typeof html5QrCodeInstance.resume === 'function') {
                    try {
                        html5QrCodeInstance.resume();
                    } catch (error) {
                        console.error('No se pudo reanudar el lector QR', error);
                    }
                }
            }

            modalClose?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            function handleResponse(data, viaCamera = false) {
                const status = data?.status ?? 'error';
                const person = data?.person ?? null;
                const summary = formatPersonSummary(person);
                const timestampLabel = person?.checked_in_at_formatted || person?.checked_in_at || '';

                let message = data?.message ?? 'No se pudo interpretar la respuesta.';
                if (status === 'ok' && summary) {
                    message = `Acceso concedido para ${summary}.`;
                    if (timestampLabel) {
                        message += ` Ingreso registrado el ${timestampLabel}.`;
                    }
                } else if (status === 'used' && summary) {
                    message = timestampLabel
                        ? `${summary} ya ingresó el ${timestampLabel}.`
                        : `${summary} ya ingresó anteriormente.`;
                }

                const success = status === 'ok';
                const detailsHtml = buildDetails(person, status);

                if (viaCamera) {
                    let title = success ? 'QR válido' : 'QR no válido';
                    let tone = success ? 'success' : 'error';
                    if (status === 'used') {
                        title = 'QR ya utilizado';
                        tone = 'error';
                    } else if (status === 'not_found') {
                        title = 'QR desconocido';
                        tone = 'error';
                    }
                    openModal(title, message, tone, detailsHtml);
                } else {
                    showResult(message, success, detailsHtml);
                }
            }

            async function validateCode(code, { viaCamera = false } = {}) {
                if (!code) return;
                try {
                    const response = await fetch('scan.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code })
                    });
                    const data = await response.json();
                    handleResponse(data, viaCamera);
                } catch (error) {
                    const fallback = {
                        status: 'error',
                        message: 'No se pudo validar el código. Verificá tu conexión.'
                    };
                    handleResponse(fallback, viaCamera);
                }
            }

            if (manualButton) {
                manualButton.addEventListener('click', () => {
                    validateCode(manualInput.value.trim(), { viaCamera: false });
                });
                manualInput?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        validateCode(manualInput.value.trim(), { viaCamera: false });
                    }
                });
            }

            const qrContainer = document.getElementById('qr-reader');
            if (typeof Html5Qrcode === 'function' && qrContainer) {
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                html5QrCodeInstance = new Html5Qrcode('qr-reader');
                const qrCodeSuccessCallback = (decodedText) => {
                    if (modalVisible) {
                        return;
                    }
                    if (html5QrCodeInstance && typeof html5QrCodeInstance.pause === 'function') {
                        html5QrCodeInstance.pause(true);
                    }
                    validateCode(decodedText, { viaCamera: true });
                };
                html5QrCodeInstance.start({ facingMode: 'environment' }, config, qrCodeSuccessCallback).catch(() => {
                    showResult('No se pudo acceder a la cámara. Intentá nuevamente o usá el código manual.', false);
                });
            }
        });
    </script>
</body>
</html>
