<?php

declare(strict_types=1);

$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
)));

return [
    'trusted_proxies' => $trustedProxies,
    'trusted_headers' => ['cf-connecting-ip', 'x-forwarded-for'],
];
