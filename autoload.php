<?php

declare(strict_types=1);

/**
 * Ruční PSR-4 autoloader bez Composeru.
 *
 * Mapuje namespace prefix "App\" na adresář /src.
 * Příklad: App\Controller\TaskController -> src/Controller/TaskController.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
