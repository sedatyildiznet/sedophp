<?php

declare(strict_types=1);

get('/', 'HomeController@index');

get('/health', static fn () => json([
    'ok' => true,
    'framework' => 'SedoPHP',
    'version' => trim((string) file_get_contents(app()->path('VERSION'))),
]));
