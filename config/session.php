<?php

declare(strict_types=1);

return [
    'name' => (string) env('SESSION_NAME', 'sedophp_session'),
    'secure' => (bool) env('SESSION_SECURE', false),
    'http_only' => (bool) env('SESSION_HTTP_ONLY', true),
    'same_site' => (string) env('SESSION_SAME_SITE', 'Lax'),
    'path' => (string) env('SESSION_PATH', '/'),
];
