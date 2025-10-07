<?php
require_once __DIR__ . '/includes/auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = authenticate($username, $password);
    if ($user) {
        $_SESSION['user'] = $user;
        switch ($user['role']) {
            case 'admin':
                header('Location: admin.php');
                break;
            case 'ventas':
                header('Location: ventas.php');
                break;
            case 'compras':
                header('Location: compras.php');
                break;
            default:
                header('Location: admin.php');
        }
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Remeras - Ingreso</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h1>Gestión de Remeras</h1>
        <p class="subtitle">Inicie sesión para acceder a su módulo.</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="card">
            <label for="username">Usuario</label>
            <input type="text" name="username" id="username" required>

            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Ingresar</button>
        </form>
        <div class="info">
            <p><strong>Accesos iniciales:</strong></p>
            <ul>
                <li>Administrador: admin / admin123</li>
                <li>Ventas: ventas / ventas123</li>
                <li>Compras: compras / compras123</li>
            </ul>
        </div>
    </div>
</body>
</html>
