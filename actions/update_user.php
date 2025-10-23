<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin']);

$username = trim($_POST['username'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if ($username === '' || $newPassword === '') {
    redirect_to('modules/admin.php?error=1');
}

$users = load_users();
$updated = false;
foreach ($users as &$user) {
    if (strcasecmp($user['username'], $username) === 0) {
        $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = true;
        break;
    }
}
unset($user);

if (!$updated) {
    redirect_to('modules/admin.php?error=1');
}

save_users($users);
redirect_to('modules/admin.php?success=1');
