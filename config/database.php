<?php

declare(strict_types=1);

$sqlite = (string) env('DB_SQLITE', 'storage/database.sqlite');
$isAbsolute = str_starts_with($sqlite, '/')
    || preg_match('/^[A-Za-z]:[\\\\\/]/', $sqlite) === 1;

if (!$isAbsolute) {
    $sqlite = dirname(__DIR__) . '/' . ltrim(str_replace('\\\\', '/', $sqlite), '/');
}

return [
    'driver' => (string) env('DB_DRIVER', 'mysql'),
    'host' => (string) env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_NAME', 'sedophp'),
    'username' => (string) env('DB_USER', 'root'),
    'password' => (string) env('DB_PASS', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    'sqlite' => $sqlite,
    'foreign_keys' => (bool) env('DB_FOREIGN_KEYS', true),
    'log_queries' => (bool) env('DB_LOG_QUERIES', false),
    'slow_query_ms' => (int) env('DB_SLOW_QUERY_MS', 0),
];
