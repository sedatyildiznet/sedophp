<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$composer = $basePath . '/vendor/autoload.php';

if (is_file($composer)) {
    require_once $composer;
    return;
}

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefixes = [
        'SedoPHP\\' => $basePath . '/src/',
        'App\\' => $basePath . '/app/',
        'Database\\Seeders\\' => $basePath . '/database/seeders/',
        'Database\\Factories\\' => $basePath . '/database/factories/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }
});

require_once $basePath . '/src/helpers.php';
