<?php

declare(strict_types=1);

return [
    'table' => (string) env('AUTH_TABLE', 'users'),
    'id' => (string) env('AUTH_ID', 'id'),
    'identity' => (string) env('AUTH_IDENTITY', 'email'),
    'password' => (string) env('AUTH_PASSWORD', 'password'),
    'login_path' => (string) env('AUTH_LOGIN_PATH', '/login'),
    'login_max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 60),
    'password_reset_ttl' => (int) env('AUTH_PASSWORD_RESET_TTL', 3600),
    'email_verification_ttl' => (int) env('AUTH_EMAIL_VERIFICATION_TTL', 86400),
    'session_key' => '_sedo_auth_id',
    'api_token_table' => (string) env('AUTH_API_TOKEN_TABLE', 'api_tokens'),
    'jwt_secret' => (string) env('JWT_SECRET', ''),
    'jwt_issuer' => (string) env('JWT_ISSUER', ''),
];
