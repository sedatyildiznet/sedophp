<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Security\SignedUrl;

final class SignedUrlMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (SignedUrl::validate($request)) {
            return $next();
        }

        return $request->expectsJson()
            ? Response::json(['error' => 'Invalid or expired signed URL'], 403)
            : new Response('<h1>403</h1><p>Invalid or expired signed URL.</p>', 403, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
    }
}
