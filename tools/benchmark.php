<?php

declare(strict_types=1);

use SedoPHP\Core\Config;
use SedoPHP\Database\Database;
use SedoPHP\Database\Model;

$processStartedAt = hrtime(true);

/** @var \SedoPHP\Core\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$bootMs = (hrtime(true) - $processStartedAt) / 1_000_000;

final class SedoBenchmarkUser extends Model
{
    protected string $table = 'benchmark_users';
    protected array $fillable = ['name', 'email'];
}

$measure = static function (callable $callback): float {
    $startedAt = hrtime(true);
    $callback();
    return (hrtime(true) - $startedAt) / 1_000_000;
};

$configReadMs = $measure(static function (): void {
    for ($i = 0; $i < 10000; $i++) {
        Config::get('app.name');
        Config::get('database.driver');
    }
});

$route = $app->router()->get('/__benchmark/users/{id}', static fn (string $id): string => $id)
    ->name('__benchmark.users.show');

$routeGenerationMs = $measure(static function () use ($app): void {
    for ($i = 1; $i <= 10000; $i++) {
        $app->router()->pathFor('__benchmark.users.show', [
            'id' => $i,
            'tab' => 'posts',
        ]);
    }
});

$databaseMs = null;
$hydrationMs = null;

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure([
        'driver' => 'sqlite',
        'sqlite' => ':memory:',
        'foreign_keys' => true,
    ]);

    $pdo = Database::pdo();
    $pdo->exec('CREATE TABLE benchmark_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL
    )');

    Database::transaction(static function (): void {
        for ($i = 1; $i <= 1000; $i++) {
            Database::table('benchmark_users')->insert([
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.test',
            ]);
        }
    });

    $rows = [];

    $databaseMs = $measure(static function () use (&$rows): void {
        $rows = Database::table('benchmark_users')
            ->orderBy('id')
            ->get();
    });

    $hydrationMs = $measure(static function () use ($rows): void {
        foreach ($rows as $row) {
            new SedoBenchmarkUser($row);
        }
    });
}

$results = [
    'php' => PHP_VERSION,
    'sedophp' => trim((string) file_get_contents(dirname(__DIR__) . '/VERSION')),
    'bootstrap_ms' => round($bootMs, 3),
    'config_reads_20000_ms' => round($configReadMs, 3),
    'route_generation_10000_ms' => round($routeGenerationMs, 3),
    'sqlite_select_1000_ms' => $databaseMs === null ? null : round($databaseMs, 3),
    'model_hydration_1000_ms' => $hydrationMs === null ? null : round($hydrationMs, 3),
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 3),
];

if (in_array('--json', $argv, true)) {
    echo json_encode(
        $results,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    exit(0);
}

echo "SedoPHP local benchmark\n\n";

foreach ($results as $label => $value) {
    echo str_pad($label, 30) . ': ' . ($value === null ? 'n/a' : (string) $value) . PHP_EOL;
}

echo "\nThese numbers are for regression tracking on the current machine, not cross-framework marketing comparisons.\n";
