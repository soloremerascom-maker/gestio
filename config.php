<?php
const DATA_DIR = __DIR__ . '/data';
const ORDERS_FILE = DATA_DIR . '/orders.json';
const USERS_FILE = DATA_DIR . '/users.json';
const EMAIL_LOG_FILE = DATA_DIR . '/email_log.txt';
const SYSTEM_EMAILS = [
    'compras' => 'compras@example.com',
    'produccion' => 'produccion@example.com'
];

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}
