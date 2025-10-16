<?php
require_once __DIR__ . '/../lib/helpers.php';
authorize(['admin']);

$username = trim($_POST['username'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if ($username === '' || $newPassword === '') {
    header('Location: /modules/admin.php?error=1');
    exit;
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
    header('Location: /modules/admin.php?error=1');
    exit;
}

save_users($users);
header('Location: /modules/admin.php?success=1');
exit;
