<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'DCManage\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/Version.php';
