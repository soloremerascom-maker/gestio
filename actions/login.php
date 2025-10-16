<?php
require_once __DIR__ . '/../lib/helpers.php';
ensure_session();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$users = load_users();
$foundUser = null;
foreach ($users as $user) {
    if (strcasecmp($user['username'] ?? '', $username) === 0) {
        $foundUser = $user;
        break;
    }
}

if ($foundUser && password_verify($password, $foundUser['password'] ?? '')) {
    $_SESSION['user'] = [
        'username' => $foundUser['username'],
        'role' => $foundUser['role'],
        'name' => $foundUser['name'] ?? $foundUser['username'],
    ];
    header('Location: /dashboard.php');
    exit;
}

header('Location: /index.php?error=1');
exit;
