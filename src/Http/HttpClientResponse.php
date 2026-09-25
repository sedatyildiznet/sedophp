<?php

declare(strict_types=1);

namespace SedoPHP\Http;

use JsonException;
use RuntimeException;

final class HttpClientResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers = [],
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('HTTP response body is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('HTTP response JSON must decode to an array or object.');
        }

        return $decoded;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
