<?php
require_once __DIR__ . '/lib/helpers.php';
ensure_session();

if (isset($_SESSION['user'])) {
    redirect_to('dashboard.php');
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Remeras - Ingreso</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; }
        .login-container { max-width: 420px; margin: 8% auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; }
        label { display: block; margin-top: 16px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 6px; }
        button { width: 100%; padding: 12px; margin-top: 24px; background: #0052cc; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #003d99; }
        .error { color: #c0392b; background: #fbeaea; padding: 12px; border-radius: 4px; margin-top: 16px; }
        .footer { text-align: center; margin-top: 24px; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Gestión de Remeras</h1>
        <?php if ($error): ?>
            <div class="error">Credenciales inválidas, intenta nuevamente.</div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars(app_url('actions/login.php')); ?>" method="post">
            <label for="username">Usuario</label>
            <input type="text" name="username" id="username" required autofocus>

            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Ingresar</button>
        </form>
        <div class="footer">© <?php echo date('Y'); ?> Gestión de Remeras Personalizadas</div>
    </div>
</body>
</html>
