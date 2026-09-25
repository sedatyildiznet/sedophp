<?php

declare(strict_types=1);

use SedoPHP\Cache\Cache;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$directory = sys_get_temp_dir() . '/sedophp_concurrency_' . getmypid();
$workerDirectory = $argv[2] ?? $directory;
Cache::configure(['path' => $workerDirectory, 'prefix' => 'stress_'], dirname(__DIR__));

if (($argv[1] ?? '') === 'worker') {
    for ($i = 0; $i < 100; $i++) { Cache::increment('counter', 1, 60); }
    exit(0);
}

$processes = [];
for ($i = 0; $i < 4; $i++) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' worker ' . escapeshellarg($directory);
    $processes[] = proc_open($command, [], $pipes);
}
foreach ($processes as $process) {
    if (!is_resource($process) || proc_close($process) !== 0) {
        fwrite(STDERR, "Concurrency worker failed.\n");
        exit(1);
    }
}
if (Cache::get('counter') !== 400) {
    fwrite(STDERR, 'Expected 400 atomic increments, got ' . var_export(Cache::get('counter'), true) . "\n");
    exit(1);
}
foreach (glob($directory . '/*') ?: [] as $file) { @unlink($file); }
@rmdir($directory);
echo "[PASS] cache concurrency stress\n";
