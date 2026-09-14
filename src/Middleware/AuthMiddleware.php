<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Auth\Auth;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return $next();
        }
        return $request->expectsJson()
            ? Response::json(['error' => 'Unauthenticated'], 401)
            : Response::redirect(Auth::loginPath());
    }
}
