<?php
session_start();

const DATA_USERS = __DIR__ . '/../data/users.json';

function load_users(): array
{
    if (!file_exists(DATA_USERS)) {
        return [];
    }
    $json = file_get_contents(DATA_USERS);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_users(array $users): void
{
    file_put_contents(DATA_USERS, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function ensure_logged_in(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

function ensure_role(array $roles): void
{
    ensure_logged_in();
    $userRole = $_SESSION['user']['role'] ?? null;
    if (!in_array($userRole, $roles, true)) {
        header('Location: index.php');
        exit;
    }
}

function authenticate(string $username, string $password): ?array
{
    $users = load_users();
    foreach ($users as $user) {
        if (strcasecmp($user['username'], $username) === 0 && password_verify($password, $user['password'])) {
            return $user;
        }
    }
    return null;
}

function update_user_password(string $username, string $newPassword): bool
{
    $users = load_users();
    foreach ($users as &$user) {
        if (strcasecmp($user['username'], $username) === 0) {
            $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            save_users($users);
            return true;
        }
    }
    return false;
}

function ensure_default_users(): void
{
    if (file_exists(DATA_USERS) && filesize(DATA_USERS) > 0) {
        return;
    }

    $defaults = [
        [
            'username' => 'admin',
            'role' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
        ],
        [
            'username' => 'ventas',
            'role' => 'ventas',
            'password' => password_hash('ventas123', PASSWORD_DEFAULT),
        ],
        [
            'username' => 'compras',
            'role' => 'compras',
            'password' => password_hash('compras123', PASSWORD_DEFAULT),
        ],
    ];

    if (!is_dir(dirname(DATA_USERS))) {
        mkdir(dirname(DATA_USERS), 0775, true);
    }

    save_users($defaults);
}

ensure_default_users();
