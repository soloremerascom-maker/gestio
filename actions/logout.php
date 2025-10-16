<?php
require_once __DIR__ . '/../lib/helpers.php';
ensure_session();
$_SESSION = [];
session_destroy();
header('Location: /index.php');
exit;
