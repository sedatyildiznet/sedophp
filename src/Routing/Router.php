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

    public function get(string $pattern, mixed $action): Route { return $this->add('GET', $pattern, $action); }
    public function post(string $pattern, mixed $action): Route { return $this->add('POST', $pattern, $action); }
    public function put(string $pattern, mixed $action): Route { return $this->add('PUT', $pattern, $action); }
    public function patch(string $pattern, mixed $action): Route { return $this->add('PATCH', $pattern, $action); }
    public function delete(string $pattern, mixed $action): Route { return $this->add('DELETE', $pattern, $action); }

    public function add(string $method, string $pattern, mixed $action): Route
    {
        $method = strtoupper($method);
        $pattern = self::normalize($pattern);

        foreach ($this->routes as $existing) {
            if ($existing->method === $method && $existing->pattern === $pattern) {
                throw new RuntimeException("Duplicate route: {$method} {$pattern}");
            }
        }

        $route = new Route($method, $pattern, $action);
        $this->routes[] = $route;
        return $route;
    }

    public function alias(string $name, string|callable $middleware): void
    {
        $this->aliases[$name] = $middleware;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
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
            $pipeline = array_reverse($route->middlewareNames());

            foreach ($pipeline as $name) {
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

    private static function normalize(string $pattern): string
    {
        $pattern = '/' . ltrim($pattern, '/');
        return $pattern !== '/' ? rtrim($pattern, '/') : '/';
    }
}
