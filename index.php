<?php
require_once __DIR__ . '/src/helpers.php';
ensureStorage();

$success = isset($_GET['success']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiesta Exclusiva - Registro de Invitados</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main>
        <div class="card">
            <div class="brand-hero">
                <img src="tres-logos.webp" alt="Identidad Solo Deportes" loading="lazy">
            </div>
            <h1>Fiesta Exclusiva 2025</h1>
            <p class="lead">Registrate para recibir tu acceso digital único. Presentalo el día del evento y viví una noche inolvidable.</p>
            <?php if ($success): ?>
                <div class="alert alert-success">Registro exitoso. Revisá tu casilla de correo para obtener tu código QR.</div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form id="registrationForm" action="register.php" method="post" novalidate>
                <fieldset>
                    <legend>¿Quién sos?</legend>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="profile_type" value="empleado" checked>
                            Empleado/a
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="profile_type" value="proveedor">
                            Proveedor/a
                        </label>
                    </div>
                </fieldset>

                <div class="grid two-columns" id="commonFields">
                    <div class="input-group">
                        <label for="first_name">Nombre</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="input-group">
                        <label for="last_name">Apellido</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="input-group">
                        <label for="email">Correo electrónico</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="phone">Teléfono</label>
                        <input type="tel" id="phone" name="phone" placeholder="Ej: 11 5555 4444" required>
                    </div>
                </div>

                <div id="employeeFields">
                    <div class="grid two-columns">
                        <div class="input-group">
                            <label for="dni_legajo">DNI o Legajo</label>
                            <input type="text" id="dni_legajo" name="dni_legajo" required>
                        </div>
                        <div class="input-group">
                            <label for="branch">Sucursal / Área</label>
                            <select id="branch" name="branch" required>
                                <option value="">-- Seleccione una Sucursal/Área --</option>
                                <option value="SUC. 01 - MERLO (SD)">SUC. 01 - MERLO (SD)</option>
                                <option value="SUC. 02 - SAN JUSTO (SU)">SUC. 02 - SAN JUSTO (SU)</option>
                                <option value="SUC. 03 - MERLO (SD)">SUC. 03 - MERLO (SD)</option>
                                <option value="SUC. 04 - PADUA (SD)">SUC. 04 - PADUA (SD)</option>
                                <option value="SUC. 05 - ITUZAINGÓ (SD)">SUC. 05 - ITUZAINGÓ (SD)</option>
                                <option value="SUC. 06 - LANÚS (SD)">SUC. 06 - LANÚS (SD)</option>
                                <option value="SUC. 07 - MERLO (SD)">SUC. 07 - MERLO (SD)</option>
                                <option value="SUC. 08 - MORENO (SD)">SUC. 08 - MORENO (SD)</option>
                                <option value="SUC. 10 - CASTELAR (SD)">SUC. 10 - CASTELAR (SD)</option>
                                <option value="SUC. 11 - LAFERRERE (SD)">SUC. 11 - LAFERRERE (SD)</option>
                                <option value="SUC. 12 - SAN MIGUEL (SD)">SUC. 12 - SAN MIGUEL (SD)</option>
                                <option value="SUC. 13 - FLORES (SD)">SUC. 13 - FLORES (SD)</option>
                                <option value="SUC. 14 - LUJÁN (SD)">SUC. 14 - LUJÁN (SD)</option>
                                <option value="SUC. 15 - FLORIDA (SD)">SUC. 15 - FLORIDA (SD)</option>
                                <option value="SUC. 16 - CABALLITO (SD)">SUC. 16 - CABALLITO (SD)</option>
                                <option value="SUC. 17 - BELGRANO (SD)">SUC. 17 - BELGRANO (SD)</option>
                                <option value="SUC. 19 - FLORES 2 (SD)">SUC. 19 - FLORES 2 (SD)</option>
                                <option value="SUC. 20 - VILLA DEL PARQUE (SD)">SUC. 20 - VILLA DEL PARQUE (SD)</option>
                                <option value="SUC. 21 - SAN JUSTO (SD)">SUC. 21 - SAN JUSTO (SD)</option>
                                <option value="SUC. 22 - MARIANO ACOSTA (SD)">SUC. 22 - MARIANO ACOSTA (SD)</option>
                                <option value="SUC. 23 - MARIANO ACOSTA (SU)">SUC. 23 - MARIANO ACOSTA (SU)</option>
                                <option value="SUC. 24 - CAMPANA (SU)">SUC. 24 - CAMPANA (SU)</option>
                                <option value="SUC. 25 - LAFERRERE (SU)">SUC. 25 - LAFERRERE (SU)</option>
                                <option value="SUC. 26 - VILLA CRESPO (SD)">SUC. 26 - VILLA CRESPO (SD)</option>
                                <option value="SUC. 27 - INTERNET">SUC. 27 - INTERNET</option>
                                <option value="SUC. 28 - MORENO 2 (SD)">SUC. 28 - MORENO 2 (SD)</option>
                                <option value="SUC. 29 - CABALLITO (SU)">SUC. 29 - CABALLITO (SU)</option>
                                <option value="SUC. 30 - BARRIO NORTE">SUC. 30 - BARRIO NORTE</option>
                                <option value="SUC. 31 - MERLO (SU)">SUC. 31 - MERLO (SU)</option>
                                <option value="SUC. 32 - LA PLATA (SD)">SUC. 32 - LA PLATA (SD)</option>
                                <option value="SUC. 33 - BALFIELD (SD)">SUC. 33 - BALFIELD (SD)</option>
                                <option value="SUC. 34 - MORENO (SD)">SUC. 34 - MORENO (SD)</option>
                                <option value="SUC. 35 - LIBERTAD (SD)">SUC. 35 - LIBERTAD (SD)</option>
                                <option value="SUC. 36 - RAMOS MEJÍA (SD)">SUC. 36 - RAMOS MEJÍA (SD)</option>
                                <option value="SUC. 37 - CAMPANA (SD)">SUC. 37 - CAMPANA (SD)</option>
                                <option value="SUC. 43 - LUJÁN 2 (SD)">SUC. 43 - LUJÁN 2 (SD)</option>
                                <option value="OTRO (Completar derecha)">OTRO (Completar derecha)</option>
                                <option value="MARKETING">MARKETING</option>
                                <option value="ADMINISTRACIÓN">ADMINISTRACIÓN</option>
                                <option value="RRHH">RRHH</option>
                                <option value="ELECTRO">ELECTRO</option>
                                <option value="SISTEMAS">SISTEMAS</option>
                                <option value="DEPOSITO">DEPOSITO</option>
                                <option value="800">800</option>
                                <option value="TESET">TESET</option>
                                <option value="COMPRAS">COMPRAS</option>
                                <option value="SUPERVISORES">SUPERVISORES</option>
                            </select>
                        </div>
                        <div class="input-group hidden" id="otherBranchGroup">
                            <label for="other_branch">Otra Sucursal / Área</label>
                            <input type="text" id="other_branch" name="other_branch">
                        </div>
                    </div>
                </div>

                <div id="providerFields" class="hidden">
                    <div class="grid two-columns">
                        <div class="input-group">
                            <label for="dni_provider">DNI</label>
                            <input type="text" id="dni_provider" name="dni_provider">
                        </div>
                        <div class="input-group">
                            <label for="company">Empresa</label>
                            <input type="text" id="company" name="company">
                        </div>
                    </div>
                </div>

                <div class="notice">
                    Al finalizar recibirás un correo con tu código QR personal, un código de respaldo y todos los datos para validar tu ingreso.
                </div>

                <button class="button" type="submit">Quiero mi invitación</button>
            </form>
        </div>
    </main>
    <script src="assets/scripts.js"></script>
</body>
</html>
