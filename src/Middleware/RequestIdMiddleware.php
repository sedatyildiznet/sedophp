<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Core\RequestContext;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $existing = $request->attribute('request_id');

        $requestId = is_string($existing) && $existing !== ''
            ? $existing
            : RequestContext::begin((string) $request->header('x-request-id', ''));

        $request->setAttribute('request_id', $requestId);

        return $next()->withHeader('X-Request-Id', $requestId);
    }
}
