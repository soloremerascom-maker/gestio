<?php
require_once __DIR__ . '/../lib/helpers.php';
ensure_session();
$_SESSION = [];
session_destroy();
redirect_to('index.php');
