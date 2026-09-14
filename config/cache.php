<?php

declare(strict_types=1);

return [
    'path' => (string) env('CACHE_PATH', 'storage/cache'),
    'prefix' => (string) env('CACHE_PREFIX', 'sedo_'),
];
