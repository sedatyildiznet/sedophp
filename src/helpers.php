<?php

declare(strict_types=1);

use SedoPHP\Auth\ApiToken;
use SedoPHP\Auth\Auth;
use SedoPHP\Cache\Cache;
use SedoPHP\Core\Application;
use SedoPHP\Core\Config;
use SedoPHP\Core\Env;
use SedoPHP\Core\Logger;
use SedoPHP\Database\Database;
use SedoPHP\Database\QueryBuilder;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Http\UploadedFile;
use SedoPHP\Mail\Mailer;
use SedoPHP\Queue\Queue;
use SedoPHP\Routing\Route;
use SedoPHP\Security\Csrf;
use SedoPHP\Security\Jwt;
use SedoPHP\Security\RateLimiter;
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
        $hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($referer !== '/' && $hostHeader !== '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $currentScheme = $https ? 'https' : 'http';
            $current = parse_url($currentScheme . '://' . $hostHeader);
            $target = parse_url($referer);

            if (is_array($current) && is_array($target) && isset($target['host'], $current['host'])) {
                $targetScheme = strtolower((string) ($target['scheme'] ?? $currentScheme));
                $targetPort = (int) ($target['port'] ?? ($targetScheme === 'https' ? 443 : 80));
                $currentPort = (int) ($current['port'] ?? ($currentScheme === 'https' ? 443 : 80));

                $sameOrigin = hash_equals(strtolower((string) $current['host']), strtolower((string) $target['host']))
                    && hash_equals($currentScheme, $targetScheme)
                    && $currentPort === $targetPort;

                if (!$sameOrigin) {
                    $referer = '/';
                }
            }
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

if (!function_exists('cache_get')) {
    function cache_get(string $key, mixed $default = null): mixed { return Cache::get($key, $default); }
}

if (!function_exists('cache_put')) {
    function cache_put(string $key, mixed $value, ?int $ttlSeconds = null): void { Cache::put($key, $value, $ttlSeconds); }
}

if (!function_exists('cache_remember')) {
    function cache_remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::remember($key, $ttlSeconds, $callback);
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): void { Cache::forget($key); }
}

if (!function_exists('jwt_encode')) {
    /** @param array<string,mixed> $claims */
    function jwt_encode(array $claims, int $ttlSeconds = 3600): string { return Jwt::encode($claims, $ttlSeconds); }
}

if (!function_exists('jwt_decode')) {
    /** @return array<string,mixed>|null */
    function jwt_decode(string $token): ?array { return Jwt::decode($token); }
}

if (!function_exists('jwt_claim')) {
    function jwt_claim(string $key, mixed $default = null): mixed
    {
        $claims = request()->attribute('jwt', []);
        return is_array($claims) ? ($claims[$key] ?? $default) : $default;
    }
}

if (!function_exists('api_token_issue')) {
    /** @param list<string> $abilities */
    function api_token_issue(
        int|string $userId,
        string $name = 'default',
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): string {
        return ApiToken::issue($userId, $name, $abilities, $expiresAt);
    }
}

if (!function_exists('api_token_revoke')) {
    function api_token_revoke(string $token): bool { return ApiToken::revoke($token); }
}

if (!function_exists('token_can')) {
    function token_can(string $ability): bool
    {
        $token = request()->attribute('api_token');
        return is_array($token) && ApiToken::can($token, $ability);
    }
}

if (!function_exists('rate_limit')) {
    /** @return array{allowed:bool,remaining:int,retry_after:int,attempts:int} */
    function rate_limit(string $key, int $maxAttempts = 60, int $decaySeconds = 60): array
    {
        return RateLimiter::hit($key, $maxAttempts, $decaySeconds);
    }
}

if (!function_exists('mail_send')) {
    /** @param string|list<string> $to @param array<string,string> $headers */
    function mail_send(
        string|array $to,
        string $subject,
        string $html,
        ?string $text = null,
        array $headers = [],
    ): bool {
        return Mailer::send($to, $subject, $html, $text, $headers);
    }
}

if (!function_exists('queue_push')) {
    /** @param class-string<\SedoPHP\Queue\JobInterface> $job @param array<string,mixed> $payload */
    function queue_push(string $job, array $payload = [], int $delaySeconds = 0, int $maxAttempts = 3): int
    {
        return Queue::push($job, $payload, $delaySeconds, $maxAttempts);
    }
}

if (!function_exists('queue_work')) {
    function queue_work(int $limit = 10): int { return Queue::work($limit); }
}

if (!function_exists('log_info')) {
    /** @param array<string, mixed> $context */
    function log_info(string $message, array $context = []): void { Logger::info($message, $context); }
}

if (!function_exists('log_error')) {
    /** @param array<string, mixed> $context */
    function log_error(string $message, array $context = []): void { Logger::error($message, $context); }
}
