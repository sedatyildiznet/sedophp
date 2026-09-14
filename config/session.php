<?php

declare(strict_types=1);

return [
    'name' => (string) env('SESSION_NAME', 'sedophp_session'),
    'secure' => (bool) env('SESSION_SECURE', false),
    'same_site' => 'Lax',
];
