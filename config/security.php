<?php

declare(strict_types=1);

return [
    'cors_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGINS', ''))))),
    'cors_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'cors_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-CSRF-Token'],
    'cors_expose_headers' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_EXPOSE_HEADERS', ''))))),
    'cors_credentials' => filter_var(env('CORS_ALLOW_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
    'cors_max_age' => max(0, (int) env('CORS_MAX_AGE', 600)),
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => (string) env('CONTENT_SECURITY_POLICY', ''),
    ],
];
