<?php

declare(strict_types=1);

namespace SedoPHP\Routing;

use RuntimeException;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\MiddlewareInterface;
use SedoPHP\Middleware\ParameterizedMiddlewareInterface;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, class-string<MiddlewareInterface>|class-string<ParameterizedMiddlewareInterface>|callable> */
    private array $aliases = [];

    /** @var list<string> */
    private array $globalMiddleware = [];

    /** @var list<array{prefix:string,middleware:list<string>}> */
    private array $groups = [];

    public function get(string $pattern, mixed $action): Route { return $this->add('GET', $pattern, $action); }
    public function post(string $pattern, mixed $action): Route { return $this->add('POST', $pattern, $action); }
    public function put(string $pattern, mixed $action): Route { return $this->add('PUT', $pattern, $action); }
    public function patch(string $pattern, mixed $action): Route { return $this->add('PATCH', $pattern, $action); }
    public function delete(string $pattern, mixed $action): Route { return $this->add('DELETE', $pattern, $action); }

    public function add(string $method, string $pattern, mixed $action): Route
    {
        $method = strtoupper($method);
        [$prefix, $middleware] = $this->groupContext();
        $pattern = self::combine($prefix, $pattern);

        foreach ($this->routes as $existing) {
            if ($existing->method === $method && $existing->pattern === $pattern) {
                throw new RuntimeException("Duplicate route: {$method} {$pattern}");
            }
        }

        $route = new Route($method, $pattern, $action);
        if ($middleware !== []) {
            $route->middleware(...$middleware);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function alias(string $name, string|callable $middleware): void
    {
        $this->aliases[$name] = $middleware;
    }

    public function middleware(string ...$names): void
    {
        foreach ($names as $name) {
            if ($name !== '' && !in_array($name, $this->globalMiddleware, true)) {
                $this->globalMiddleware[] = $name;
            }
        }
    }

    /**
     * @param array{prefix?:string,middleware?:string|list<string>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = self::normalizePrefix((string) ($attributes['prefix'] ?? ''));
        $middleware = $attributes['middleware'] ?? [];
        $middleware = is_string($middleware) ? [$middleware] : array_values($middleware);

        foreach ($middleware as $name) {
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('Route group middleware names must be non-empty strings.');
            }
        }

        $this->groups[] = ['prefix' => $prefix, 'middleware' => $middleware];

        try {
            $callback();
        } finally {
            array_pop($this->groups);
        }
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @param array<string,mixed> $parameters */
    public function pathFor(string $name, array $parameters = []): string
    {
        $matches = array_values(array_filter(
            $this->routes,
            static fn (Route $route): bool => $route->routeName() === $name
        ));

        if ($matches === []) {
            throw new RuntimeException("Named route not found: {$name}");
        }

        if (count($matches) > 1) {
            throw new RuntimeException("Duplicate route name: {$name}");
        }

        $used = [];
        $path = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $match) use ($parameters, &$used, $name): string {
                $key = $match[1];
                if (!array_key_exists($key, $parameters)) {
                    throw new RuntimeException("Missing route parameter {$key} for {$name}.");
                }

                $value = $parameters[$key];
                if (!is_scalar($value) && !($value instanceof \Stringable)) {
                    throw new RuntimeException("Route parameter {$key} must be scalar or stringable.");
                }

                $used[] = $key;
                return rawurlencode((string) $value);
            },
            $matches[0]->pattern
        );

        if ($path === null) {
            throw new RuntimeException("Unable to build route path: {$name}");
        }

        $query = array_diff_key($parameters, array_flip($used));
        if ($query !== []) {
            $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $path;
    }

    public function dispatch(Request $request): Response
    {
        $destination = fn (): Response => $this->dispatchRoutes($request);

        foreach (array_reverse($this->globalMiddleware) as $name) {
            $next = $destination;
            $destination = fn (): Response => $this->runMiddleware($name, $request, $next);
        }

        return $destination();
    }

    private function dispatchRoutes(Request $request): Response
    {
        $originalMethod = $request->method();

        if ($originalMethod === 'OPTIONS') {
            $allowed = $this->allowedMethods($request->path());
            if ($allowed !== []) {
                return new Response('', 204, ['Allow' => implode(', ', $allowed)]);
            }
        }

        $method = $originalMethod === 'HEAD' ? 'GET' : $originalMethod;

        foreach ($this->routes as $route) {
            if ($route->method !== $method) {
                continue;
            }

            $params = $this->match($route->pattern, $request->path());
            if ($params === null) {
                continue;
            }

            $destination = fn (): Response => $this->normalizeResponse($this->invoke($route->action, $params));
            foreach (array_reverse($route->middlewareNames()) as $name) {
                $next = $destination;
                $destination = fn (): Response => $this->runMiddleware($name, $request, $next);
            }

            $response = $destination();
            return $originalMethod === 'HEAD' ? $response->withoutBody() : $response;
        }

        $allowed = $this->allowedMethods($request->path());
        if ($allowed !== []) {
            $allow = implode(', ', $allowed);
            return $request->expectsJson()
                ? Response::json(['error' => 'Method not allowed'], 405)->withHeader('Allow', $allow)
                : (new Response('<h1>405</h1><p>Method not allowed.</p>', 405, ['Content-Type' => 'text/html; charset=UTF-8']))
                    ->withHeader('Allow', $allow);
        }

        return $request->expectsJson()
            ? Response::json(['error' => 'Not found'], 404)
            : new Response('<h1>404</h1><p>Page not found.</p>', 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function describeAction(mixed $action): string
    {
        if (is_string($action)) {
            return $action;
        }
        if (is_array($action) && count($action) === 2) {
            $class = is_object($action[0]) ? $action[0]::class : (string) $action[0];
            return $class . '@' . (string) $action[1];
        }
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        return is_object($action) ? $action::class : gettype($action);
    }

    /** @param array<string, string> $params */
    private function invoke(mixed $action, array $params): mixed
    {
        $arguments = array_values($params);

        if (is_callable($action)) {
            return $action(...$arguments);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $class = str_contains($controller, '\\') ? $controller : 'App\\Controllers\\' . $controller;

            if (!class_exists($class)) {
                throw new RuntimeException("Controller not found: {$class}");
            }

            $instance = new $class();
            if (!method_exists($instance, $method)) {
                throw new RuntimeException("Controller method not found: {$class}@{$method}");
            }

            return $instance->{$method}(...$arguments);
        }

        throw new RuntimeException('Invalid route action. Use a closure, callable, or Controller@method.');
    }

    private function runMiddleware(string $definition, Request $request, callable $next): Response
    {
        [$name, $parameterText] = array_pad(explode(':', $definition, 2), 2, null);
        $parameters = $parameterText === null || trim($parameterText) === ''
            ? []
            : array_values(array_map('trim', explode(',', $parameterText)));

        if (!array_key_exists($name, $this->aliases)) {
            throw new RuntimeException("Middleware alias not found: {$name}");
        }

        $middleware = $this->aliases[$name];
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }

        if ($middleware instanceof ParameterizedMiddlewareInterface) {
            return $middleware->handle($request, $next, $parameters);
        }

        if ($parameters !== []) {
            throw new RuntimeException("Middleware does not accept parameters: {$name}");
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->handle($request, $next);
        }

        if (is_callable($middleware)) {
            return $this->normalizeResponse($middleware($request, $next));
        }

        throw new RuntimeException("Invalid middleware: {$name}");
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        if ($result === null) {
            return new Response('');
        }
        return new Response((string) $result, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @return list<string> */
    private function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $route) {
            if ($this->match($route->pattern, $path) !== null) {
                $methods[] = $route->method;
                if ($route->method === 'GET') {
                    $methods[] = 'HEAD';
                }
            }
        }

        if ($methods !== []) {
            $methods[] = 'OPTIONS';
        }

        $methods = array_values(array_unique($methods));
        sort($methods, SORT_STRING);
        return $methods;
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $names = [];
        $quoted = preg_quote($pattern, '#');
        $regex = preg_replace_callback('/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/', static function (array $match) use (&$names): string {
            if (!in_array($match[1], $names, true)) {
                $names[] = $match[1];
            }
            return '([^/]+)';
        }, $quoted);

        if ($regex === null || preg_match('#^' . $regex . '$#', $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($names as $index => $name) {
            $params[$name] = rawurldecode((string) ($matches[$index] ?? ''));
        }
        return $params;
    }

    /** @return array{0:string,1:list<string>} */
    private function groupContext(): array
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groups as $group) {
            $prefix = self::combine($prefix, $group['prefix']);
            array_push($middleware, ...$group['middleware']);
        }

        return [$prefix, $middleware];
    }

    private static function combine(string $prefix, string $pattern): string
    {
        $prefix = self::normalizePrefix($prefix);
        $pattern = self::normalize($pattern);

        if ($prefix === '') {
            return $pattern;
        }

        return self::normalize($prefix . ($pattern === '/' ? '' : $pattern));
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }
        return '/' . trim($prefix, '/');
    }

    private static function normalize(string $pattern): string
    {
        $pattern = '/' . ltrim($pattern, '/');
        return $pattern !== '/' ? rtrim($pattern, '/') : '/';
    }
}
