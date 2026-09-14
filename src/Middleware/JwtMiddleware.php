<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Security\Jwt;

final class JwtMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = Jwt::bearerToken((string) $request->header('authorization', ''));
        $claims = $token === null ? null : Jwt::decode($token);

        if ($claims === null) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }

        $request->setAttribute('jwt', $claims);
        return $next();
    }
}
