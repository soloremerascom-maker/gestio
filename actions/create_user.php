<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin']);

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

if ($name === '' || $username === '' || $password === '' || $role === '') {
    redirect_to('modules/admin.php?error=1');
}

$users = load_users();
foreach ($users as $user) {
    if (strcasecmp($user['username'], $username) === 0) {
        redirect_to('modules/admin.php?error=1');
    }
}

$users[] = [
    'name' => $name,
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
];

save_users($users);
redirect_to('modules/admin.php?success=1');
