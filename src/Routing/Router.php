<?php

declare(strict_types=1);

namespace SedoPHP\Routing;

use RuntimeException;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\MiddlewareInterface;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];
    /** @var array<string, class-string<MiddlewareInterface>|callable> */
    private array $aliases = [];

    public function get(string $pattern, mixed $action): Route { return $this->add('GET', $pattern, $action); }
    public function post(string $pattern, mixed $action): Route { return $this->add('POST', $pattern, $action); }
    public function put(string $pattern, mixed $action): Route { return $this->add('PUT', $pattern, $action); }
    public function patch(string $pattern, mixed $action): Route { return $this->add('PATCH', $pattern, $action); }
    public function delete(string $pattern, mixed $action): Route { return $this->add('DELETE', $pattern, $action); }

    public function add(string $method, string $pattern, mixed $action): Route
    {
        $route = new Route(strtoupper($method), self::normalize($pattern), $action);
        $this->routes[] = $route;
        return $route;
    }

    public function alias(string $name, string|callable $middleware): void
    {
        $this->aliases[$name] = $middleware;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();

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

            return $destination();
        }

        return $request->expectsJson()
            ? Response::json(['error' => 'Not found'], 404)
            : new Response('<h1>404</h1><p>Page not found.</p>', 404, ['Content-Type' => 'text/html; charset=UTF-8']);
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

        if (is_array($action) && count($action) === 2 && is_callable($action)) {
            return $action(...$arguments);
        }

        throw new RuntimeException('Invalid route action. Use a closure or Controller@method.');
    }

    private function runMiddleware(string $name, Request $request, callable $next): Response
    {
        if (!array_key_exists($name, $this->aliases)) {
            throw new RuntimeException("Middleware alias not found: {$name}");
        }

        $middleware = $this->aliases[$name];
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->handle($request, $next);
        }

        if (is_callable($middleware)) {
            $result = $middleware($request, $next);
            return $this->normalizeResponse($result);
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
