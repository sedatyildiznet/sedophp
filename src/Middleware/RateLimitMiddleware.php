<?php

declare(strict_types=1);

namespace SedoPHP\Middleware;

use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Security\RateLimiter;

final class RateLimitMiddleware implements ParameterizedMiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters): Response
    {
        $maxAttempts = max(1, (int) ($parameters[0] ?? 60));
        $decaySeconds = max(1, (int) ($parameters[1] ?? 60));
        $identity = ($request->ip() ?? 'unknown') . '|' . $request->method() . '|' . $request->path();
        $result = RateLimiter::hit(hash('sha256', $identity), $maxAttempts, $decaySeconds);

        if (!$result['allowed']) {
            $response = $request->expectsJson()
                ? Response::json(['error' => 'Too many requests'], 429)
                : new Response('Too many requests.', 429, ['Content-Type' => 'text/plain; charset=UTF-8']);

            return $response
                ->withHeader('Retry-After', (string) $result['retry_after'])
                ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        /** @var Response $response */
        $response = $next();

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
