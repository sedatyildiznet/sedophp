<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$vendor = $root . '/vendor/autoload.php';

if (is_file($vendor)) {
    fwrite(STDERR, "Fallback autoload test requires vendor/autoload.php to be absent.\n");
    exit(1);
}

require $root . '/bootstrap/autoload.php';

$classes = [
    SedoPHP\Core\Application::class,
    SedoPHP\Database\QueryBuilder::class,
    SedoPHP\Cache\FileCacheDriver::class,
    SedoPHP\Queue\DatabaseQueueDriver::class,
    SedoPHP\Filesystem\LocalFilesystemDriver::class,
    SedoPHP\Http\HttpClient::class,
    SedoPHP\Events\EventDispatcher::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "Fallback autoload failed for {$class}.\n");
        exit(1);
    }
}

if (!function_exists('db') || !function_exists('http') || !function_exists('storage')) {
    fwrite(STDERR, "Fallback autoload did not load helper functions.\n");
    exit(1);
}

echo "[PASS] Composer-free fallback autoload\n";
