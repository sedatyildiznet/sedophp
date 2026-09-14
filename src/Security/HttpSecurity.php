<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

final class HttpSecurity
{
    /** @var array<string,mixed> */
    private static array $config = [];

    /** @param array<string,mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function applyHeaders(Response $response): Response
    {
        foreach ((array) (self::$config['headers'] ?? []) as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '') {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    public static function applyCors(Response $response, Request $request): Response
    {
        $origin = trim((string) $request->header('origin', ''));
        if ($origin === '') {
            return $response;
        }

        $allowed = array_values(array_filter((array) (self::$config['cors_origins'] ?? []), 'is_string'));
        $credentials = (bool) (self::$config['cors_credentials'] ?? false);

        if (!in_array('*', $allowed, true) && !in_array($origin, $allowed, true)) {
            return $response;
        }

        $allowOrigin = in_array('*', $allowed, true) && !$credentials ? '*' : $origin;
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', (array) (self::$config['cors_methods'] ?? [])))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', (array) (self::$config['cors_headers'] ?? [])));

        if ($allowOrigin !== '*') {
            $response = $response->withHeader('Vary', self::appendVary($response, 'Origin'));
        }

        if ($credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        $expose = array_values(array_filter((array) (self::$config['cors_expose_headers'] ?? []), 'is_string'));
        if ($expose !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $expose));
        }

        if ($request->method() === 'OPTIONS') {
            $maxAge = max(0, (int) (self::$config['cors_max_age'] ?? 0));
            if ($maxAge > 0) {
                $response = $response->withHeader('Access-Control-Max-Age', (string) $maxAge);
            }
        }

        return $response;
    }

    public static function apply(Response $response, Request $request): Response
    {
        return self::applyHeaders(self::applyCors($response, $request));
    }

    private static function appendVary(Response $response, string $value): string
    {
        $current = (string) ($response->headers()['Vary'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(',', $current))));

        if (!in_array($value, $parts, true)) {
            $parts[] = $value;
        }

        return implode(', ', $parts);
    }
}
