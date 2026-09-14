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
    public static function configure(array $config): void { self::$config = $config; }

    public static function apply(Response $response, Request $request): Response
    {
        foreach ((array) (self::$config['headers'] ?? []) as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '') {
                $response = $response->withHeader($name, $value);
            }
        }

        $origin = (string) $request->header('origin', '');
        $allowed = (array) (self::$config['cors_origins'] ?? []);
        if ($origin !== '' && (in_array('*', $allowed, true) || in_array($origin, $allowed, true))) {
            $allowOrigin = in_array('*', $allowed, true) ? '*' : $origin;
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', (array) (self::$config['cors_methods'] ?? [])))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', (array) (self::$config['cors_headers'] ?? [])))
                ->withHeader('Vary', 'Origin');
        }
        return $response;
    }
}
