<?php
declare(strict_types=1);

if (!function_exists('dashscope_load_configuration')) {
    function dashscope_load_configuration(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $baseDir = __DIR__;
        $candidates = [
            'config.php',
            'config.local.php',
            'config.sample.php',
        ];

        foreach ($candidates as $file) {
            $path = $baseDir . '/' . $file;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}

dashscope_load_configuration();
