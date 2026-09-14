<?php

declare(strict_types=1);

return [
    'driver' => (string) env('DB_DRIVER', 'mysql'),
    'host' => (string) env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_NAME', 'sedophp'),
    'username' => (string) env('DB_USER', 'root'),
    'password' => (string) env('DB_PASS', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    'sqlite' => (string) env('DB_SQLITE', dirname(__DIR__) . '/storage/database.sqlite'),
];
