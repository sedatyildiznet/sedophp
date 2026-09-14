<?php

declare(strict_types=1);

return [
    'cors_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGINS', ''))))),
    'cors_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'cors_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-CSRF-Token'],
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ],
];
