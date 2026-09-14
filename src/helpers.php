<?php

declare(strict_types=1);

use SedoPHP\Auth\Auth;
use SedoPHP\Core\Application;
use SedoPHP\Core\Config;
use SedoPHP\Core\Env;
use SedoPHP\Core\Logger;
use SedoPHP\Database\Database;
use SedoPHP\Database\QueryBuilder;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Http\UploadedFile;
use SedoPHP\Routing\Route;
use SedoPHP\Security\Csrf;
use SedoPHP\Session\Session;
use SedoPHP\Validation\Validator;
use SedoPHP\View\View;

if (!function_exists('app')) {
    function app(): Application { return Application::instance(); }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed { return Env::get($key, $default); }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed { return Config::get($key, $default); }
}

if (!function_exists('request')) {
    function request(): Request { return app()->request(); }
}

if (!function_exists('input')) {
    function input(?string $key = null, mixed $default = null): mixed { return request()->input($key, $default); }
}

if (!function_exists('input_all')) {
    /** @return array<string, mixed> */
    function input_all(): array { return request()->all(); }
}

if (!function_exists('upload')) {
    function upload(string $key): ?UploadedFile { return request()->file($key); }
}

if (!function_exists('get')) {
    function get(string $pattern, mixed $action): Route { return app()->router()->get($pattern, $action); }
}

if (!function_exists('post')) {
    function post(string $pattern, mixed $action): Route { return app()->router()->post($pattern, $action); }
}

if (!function_exists('put')) {
    function put(string $pattern, mixed $action): Route { return app()->router()->put($pattern, $action); }
}

if (!function_exists('patch')) {
    function patch(string $pattern, mixed $action): Route { return app()->router()->patch($pattern, $action); }
}

if (!function_exists('delete')) {
    function delete(string $pattern, mixed $action): Route { return app()->router()->delete($pattern, $action); }
}

if (!function_exists('view')) {
    /** @param array<string, mixed> $data */
    function view(string $name, array $data = [], int $status = 200): Response { return View::render($name, $data, $status); }
}

if (!function_exists('response')) {
    /** @param array<string, string> $headers */
    function response(string $body = '', int $status = 200, array $headers = []): Response { return new Response($body, $status, $headers); }
}

if (!function_exists('json')) {
    /** @param array<string, mixed> $data */
    function json(array $data, int $status = 200): Response { return Response::json($data, $status); }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): Response { return Response::redirect($to, $status); }
}

if (!function_exists('back')) {
    function back(int $status = 302): Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $refererHost = (string) (parse_url($referer, PHP_URL_HOST) ?? '');

        if ($refererHost !== '' && $host !== '' && !hash_equals(strtolower($host), strtolower($refererHost))) {
            $referer = '/';
        }

        return redirect($referer, $status);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = (string) config('app.url', '');

        if ($base === '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $https ? 'https' : 'http';
            $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $base = $scheme . '://' . ($host !== '' ? $host : 'localhost');
        }

        return rtrim($base, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

if (!function_exists('db')) {
    function db(string $table): QueryBuilder { return Database::table($table); }
}

if (!function_exists('transaction')) {
    function transaction(callable $callback): mixed { return Database::transaction($callback); }
}

if (!function_exists('validate')) {
    /** @param array<string, mixed> $data @param array<string, string|array<int, string>> $rules @return array<string, list<string>> */
    function validate(array $data, array $rules): array { return Validator::validate($data, $rules); }
}

if (!function_exists('session')) {
    function session(string $key, mixed $default = null): mixed { return Session::get($key, $default); }
}

if (!function_exists('session_set')) {
    function session_set(string $key, mixed $value): void { Session::set($key, $value); }
}

if (!function_exists('session_forget')) {
    function session_forget(string $key): void { Session::forget($key); }
}

if (!function_exists('session_pull')) {
    function session_pull(string $key, mixed $default = null): mixed { return Session::pull($key, $default); }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value): void { Session::flash($key, $value); }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string { return Csrf::token(); }
}

if (!function_exists('csrf')) {
    function csrf(): string { return Csrf::field(); }
}

if (!function_exists('method')) {
    function method(string $method): string
    {
        $method = strtoupper($method);
        if (!in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException('Method override must be PUT, PATCH or DELETE.');
        }
        return '<input type="hidden" name="_method" value="' . $method . '">';
    }
}

if (!function_exists('login')) {
    function login(string $identity, string $password): bool { return Auth::attempt($identity, $password); }
}

if (!function_exists('logout')) {
    function logout(): void { Auth::logout(); }
}

if (!function_exists('auth')) {
    function auth(): bool { return Auth::check(); }
}

if (!function_exists('user')) {
    function user(?string $key = null, mixed $default = null): mixed
    {
        $current = Auth::user();
        if ($key === null) {
            return $current;
        }
        return $current[$key] ?? $default;
    }
}

if (!function_exists('log_info')) {
    /** @param array<string, mixed> $context */
    function log_info(string $message, array $context = []): void { Logger::info($message, $context); }
}

if (!function_exists('log_error')) {
    /** @param array<string, mixed> $context */
    function log_error(string $message, array $context = []): void { Logger::error($message, $context); }
}
