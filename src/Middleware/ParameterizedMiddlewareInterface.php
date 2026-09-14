<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

interface ParameterizedMiddlewareInterface
{
    /** @param list<string> $parameters */
    public function handle(Request $request, callable $next, array $parameters): Response;
}
