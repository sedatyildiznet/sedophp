<?php

declare(strict_types=1);

namespace SedoPHP\Http;

use JsonException;

final class Request
{
    /** @param array<string, mixed> $query @param array<string, mixed> $body @param array<string, mixed> $files @param array<string, mixed> $server @param array<string, string> $headers */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $files = [],
        private array $server = [],
        private array $headers = [],
    ) {
        $this->method = strtoupper($this->method);
        $this->path = self::normalizePath($this->path);
    }

    public static function capture(string $basePath = ''): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $headers = self::captureHeaders($_SERVER);
        $contentType = strtolower($headers['content-type'] ?? '');
        $body = $_POST;
        $raw = '';

        if (str_contains($contentType, 'application/json')) {
            $raw = (string) file_get_contents('php://input');
            if ($raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new HttpException(400, 'Invalid JSON body.');
                }
                if (!is_array($decoded)) {
                    throw new HttpException(400, 'JSON request body must be an object or array.');
                }
                $body = $decoded;
            } else {
                $body = [];
            }
        } elseif (
            in_array($method, ['PUT', 'PATCH', 'DELETE'], true)
            && str_contains($contentType, 'application/x-www-form-urlencoded')
        ) {
            $raw = (string) file_get_contents('php://input');
            parse_str($raw, $body);
        }

        if ($method === 'POST' && isset($body['_method'])) {
            $override = strtoupper((string) $body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $basePath = '/' . trim($basePath, '/');

        if ($basePath !== '/' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        return new self($method, $path, $_GET, $body, $_FILES, $_SERVER, $headers);
    }

    /** @param array<string, mixed> $input @param array<string, string> $headers */
    public static function fake(string $method, string $uri, array $input = [], array $headers = []): self
    {
        $parts = parse_url($uri);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $method = strtoupper($method);
        $body = $method === 'GET' ? [] : $input;

        if ($method === 'GET') {
            $query = array_merge($query, $input);
        }

        if ($method === 'POST' && isset($body['_method'])) {
            $override = strtoupper((string) $body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower((string) $key)] = (string) $value;
        }

        return new self($method, (string) ($parts['path'] ?? '/'), $query, $body, [], [], $normalizedHeaders);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->all();
        return $key === null ? $all : ($all[$key] ?? $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->body : ($this->body[$key] ?? $default);
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? UploadedFile::fromArray($file) : null;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('content-type', '')), 'application/json');
    }

    public function expectsJson(): bool
    {
        return str_contains(strtolower((string) $this->header('accept', '')), 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    public function ip(): ?string
    {
        return isset($this->server['REMOTE_ADDR']) ? (string) $this->server['REMOTE_ADDR'] : null;
    }

    /** @param array<string, mixed> $server @return array<string, string> */
    private static function captureHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $server['CONTENT_LENGTH'];
        }
        return $headers;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $path !== '/' ? rtrim($path, '/') : '/';
    }
}
