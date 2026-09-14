<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Auth\Auth;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return Auth::check() ? Response::redirect('/') : $next();
    }
}
