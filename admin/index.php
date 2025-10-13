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
    </style>
    <script>
        const QR_SCAN_ENDPOINT = 'scan.php';
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
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Tipo</th>
                                    <th>Dato</th>
                                    <th>Ingreso</th>
                                    <th>Código</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars(ucfirst($row['profile_type'] ?: 'especial')); ?></span></td>
                                        <td>
                                            <?php if ($row['profile_type'] === 'empleado'): ?>
                                                <?php echo htmlspecialchars($row['branch']); ?>
                                            <?php elseif ($row['profile_type'] === 'proveedor'): ?>
                                                <?php echo htmlspecialchars($row['company']); ?>
                                            <?php else: ?>
                                                Invitado especial
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['checked_in'] === '1'): ?>
                                                <span class="badge" style="background:rgba(16,185,129,0.15);color:#047857;">Ingresó</span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(239,68,68,0.12);color:#b91c1c;">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($row['offline_code']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
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

            function showResult(message, success = true) {
                if (!resultBox) return;
                resultBox.textContent = message;
                resultBox.classList.toggle('alert-error', !success);
                resultBox.classList.toggle('alert-success', success);
                resultBox.style.display = 'block';
            }

            async function validateCode(code) {
                if (!code) return;
                try {
                    const response = await fetch('scan.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code })
                    });
                    const data = await response.json();
                    showResult(data.message, data.status === 'ok');
                } catch (error) {
                    showResult('No se pudo validar el código. Verificá tu conexión.', false);
                }
            }

            if (manualButton) {
                manualButton.addEventListener('click', () => {
                    validateCode(manualInput.value.trim());
                });
                manualInput?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        validateCode(manualInput.value.trim());
                    }
                });
            }

            const qrContainer = document.getElementById('qr-reader');
            if (typeof Html5Qrcode === 'function' && qrContainer) {
                const qrCodeSuccessCallback = (decodedText) => {
                    validateCode(decodedText);
                };
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                const html5QrCode = new Html5Qrcode('qr-reader');
                html5QrCode.start({ facingMode: 'environment' }, config, qrCodeSuccessCallback).catch(() => {
                    showResult('No se pudo acceder a la cámara. Intentá nuevamente o usá el código manual.', false);
                });
            }
        });
    </script>
</body>
</html>
