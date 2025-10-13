<?php
require_once __DIR__ . '/src/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

ensureStorage();

$profileType = $_POST['profile_type'] ?? '';
$email = strtolower(trim($_POST['email'] ?? ''));

if ($profileType !== 'empleado' && $profileType !== 'proveedor') {
    header('Location: index.php?error=' . urlencode('Debés seleccionar un perfil válido.'));
    exit;
}

if (empty($email)) {
    header('Location: index.php?error=' . urlencode('El correo electrónico es obligatorio.'));
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($firstName === '' || $lastName === '' || $phone === '') {
    header('Location: index.php?error=' . urlencode('Completá todos los datos personales obligatorios.'));
    exit;
}

if ($profileType === 'empleado') {
    if (trim($_POST['dni_legajo'] ?? '') === '' || trim($_POST['branch'] ?? '') === '') {
        header('Location: index.php?error=' . urlencode('Debés indicar tu DNI/Legajo y la sucursal.'));
        exit;
    }
    if (($_POST['branch'] ?? '') === 'OTRO (Completar derecha)' && trim($_POST['other_branch'] ?? '') === '') {
        header('Location: index.php?error=' . urlencode('Ingresá la sucursal/área correspondiente.'));
        exit;
    }
} else {
    if (trim($_POST['dni_provider'] ?? '') === '' || trim($_POST['company'] ?? '') === '') {
        header('Location: index.php?error=' . urlencode('Completá DNI y empresa para proveedores.'));
        exit;
    }
}

if ($email !== SPECIAL_TEST_EMAIL && registrationExists($email)) {
    header('Location: index.php?error=' . urlencode('El correo ya tiene una invitación asignada.'));
    exit;
}

try {
    createRegistration($_POST);
} catch (Throwable $th) {
    header('Location: index.php?error=' . urlencode('No se pudo completar el registro: ' . $th->getMessage()));
    exit;
}

header('Location: index.php?success=1');
exit;
