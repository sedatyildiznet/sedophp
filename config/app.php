<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'SedoPHP'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'key' => (string) env('APP_KEY', ''),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'base_path' => trim((string) env('APP_BASE_PATH', ''), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
];
