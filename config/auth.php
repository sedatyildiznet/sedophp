<?php

declare(strict_types=1);

return [
    'table' => (string) env('AUTH_TABLE', 'users'),
    'id' => (string) env('AUTH_ID', 'id'),
    'identity' => (string) env('AUTH_IDENTITY', 'email'),
    'password' => (string) env('AUTH_PASSWORD', 'password'),
    'login_path' => (string) env('AUTH_LOGIN_PATH', '/login'),
    'session_key' => '_sedo_auth_id',
    'api_token_table' => (string) env('AUTH_API_TOKEN_TABLE', 'api_tokens'),
    'jwt_secret' => (string) env('JWT_SECRET', ''),
    'jwt_issuer' => (string) env('JWT_ISSUER', ''),
];
