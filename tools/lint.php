<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['src', 'app', 'bootstrap', 'config', 'routes', 'public', 'database', 'tests', 'tools'];
$failed = 0;
$count = 0;

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $count++;
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
        exec($command, $output, $code);

        if ($code !== 0) {
            $failed++;
            echo "[FAIL] " . $file->getPathname() . PHP_EOL;
            echo implode(PHP_EOL, $output) . PHP_EOL;
        }

        $output = [];
    }
}

$rootFiles = [$root . DIRECTORY_SEPARATOR . 'sedo'];

foreach ($rootFiles as $file) {
    if (!is_file($file)) {
        continue;
    }

    $count++;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($command, $output, $code);

    if ($code !== 0) {
        $failed++;
        echo "[FAIL] " . $file . PHP_EOL;
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }

    $output = [];
}

if ($failed === 0) {
    echo "Linted {$count} PHP files successfully." . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
