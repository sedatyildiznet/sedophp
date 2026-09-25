<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Auth\ApiToken;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;

final class ApiTokenMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = ApiToken::authenticate($request);

        if ($token === null) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }

        $request->setAttribute('api_token', $token);
        $request->setAttribute('token_user_id', $token['user_id'] ?? null);

        return $next();
    }
}
