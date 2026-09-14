<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use SedoPHP\Http\Request;
use SedoPHP\Session\Session;

final class Csrf
{
    private const KEY = '_sedo_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public static function verify(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $provided = $request->input('_token') ?? $request->header('x-csrf-token');
        return is_string($provided) && hash_equals(self::token(), $provided);
    }
}
