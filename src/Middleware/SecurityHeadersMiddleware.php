<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Security\HttpSecurity;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return HttpSecurity::applyHeaders($next());
    }
}
