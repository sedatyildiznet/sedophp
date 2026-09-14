<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
