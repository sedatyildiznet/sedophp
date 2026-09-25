<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Security\Csrf;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Csrf::verify($request)) {
            return $next();
        }
        return $request->expectsJson()
            ? Response::json(['error' => 'CSRF token mismatch'], 419)
            : new Response('<h1>419</h1><p>CSRF token mismatch.</p>', 419, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
