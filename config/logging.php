<?php

declare(strict_types=1);

return [
    'path' => (string) env('LOG_PATH', 'storage/logs/app.log'),
    'format' => strtolower((string) env('LOG_FORMAT', 'text')),
    'level' => strtolower((string) env('LOG_LEVEL', 'info')),
];
