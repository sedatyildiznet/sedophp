<?php

declare(strict_types=1);

namespace SedoPHP\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class HttpClient
{
    /** @var array<string,string> */
    private array $headers = [];
    private int $timeoutSeconds = 10;

    public function timeout(int $seconds): self
    {
        if ($seconds < 1 || $seconds > 120) {
            throw new InvalidArgumentException('HTTP timeout must be between 1 and 120 seconds.');
        }

        $clone = clone $this;
        $clone->timeoutSeconds = $seconds;
        return $clone;
    }

    public function header(string $name, string $value): self
    {
        self::assertHeader($name, $value);

        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /** @param array<string,string> $headers */
    public function headers(array $headers): self
    {
        $clone = clone $this;

        foreach ($headers as $name => $value) {
            self::assertHeader((string) $name, (string) $value);
            $clone->headers[(string) $name] = (string) $value;
        }

        return $clone;
    }

    public function bearer(string $token): self
    {
        return $this->header('Authorization', 'Bearer ' . $token);
    }

    /** @param array<string,scalar|null> $query */
    public function get(string $url, array $query = []): HttpClientResponse
    {
        if ($query !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->request('GET', $url);
    }

    /** @param array<string,scalar|null>|string $body */
    public function post(string $url, array|string $body = []): HttpClientResponse
    {
        if (is_array($body)) {
            $encoded = http_build_query($body, '', '&', PHP_QUERY_RFC3986);

            return $this
                ->header('Content-Type', 'application/x-www-form-urlencoded')
                ->request('POST', $url, $encoded);
        }

        return $this->request('POST', $url, $body);
    }

    /** @param array<string,mixed> $body */
    public function postJson(string $url, array $body): HttpClientResponse
    {
        try {
            $encoded = json_encode(
                $body,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode HTTP JSON body.', 0, $exception);
        }

        return $this
            ->header('Content-Type', 'application/json')
            ->header('Accept', 'application/json')
            ->request('POST', $url, $encoded);
    }

    public function request(string $method, string $url, ?string $body = null): HttpClientResponse
    {
        $method = strtoupper(trim($method));
        if (preg_match('/^[A-Z]+$/', $method) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP method.');
        }

        self::assertUrl($url);

        $headers = $this->headers;
        if (!self::hasHeader($headers, 'User-Agent')) {
            $headers['User-Agent'] = 'SedoPHP HTTP Client';
        }

        return function_exists('curl_init')
            ? $this->curlRequest($method, $url, $body, $headers)
            : $this->streamRequest($method, $url, $body, $headers);
    }

    /** @param array<string,string> $headers */
    private function curlRequest(string $method, string $url, ?string $body, array $headers): HttpClientResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => self::headerLines($headers),
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        if ($raw === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('HTTP request failed: ' . ($message !== '' ? $message : 'cURL error'));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        $headerText = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return new HttpClientResponse(
            $status,
            $responseBody,
            self::parseHeaderText($headerText)
        );
    }

    /** @param array<string,string> $headers */
    private function streamRequest(string $method, string $url, ?string $body, array $headers): HttpClientResponse
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", self::headerLines($headers)),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $context = stream_context_create($options);
        $responseBody = @file_get_contents($url, false, $context);

        /** @var list<string> $http_response_header */
        $responseHeaders = $http_response_header ?? [];

        if ($responseBody === false && $responseHeaders === []) {
            $error = error_get_last();
            throw new RuntimeException(
                'HTTP request failed: ' . (string) ($error['message'] ?? 'stream error')
            );
        }

        [$status, $parsedHeaders] = self::parseStreamHeaders($responseHeaders);

        return new HttpClientResponse(
            $status,
            $responseBody === false ? '' : $responseBody,
            $parsedHeaders
        );
    }

    private static function assertUrl(string $url): void
    {
        $parts = parse_url($url);

        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'HTTP client only accepts absolute http/https URLs without embedded credentials.'
            );
        }
    }

    private static function assertHeader(string $name, string $value): void
    {
        if ($name === '' || preg_match("/^[!#$%&'*+.^_|~0-9A-Za-z-]+$/", $name) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP header name.');
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException('HTTP header values cannot contain newlines.');
        }
    }

    /** @param array<string,string> $headers */
    private static function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,string> $headers @return list<string> */
    private static function headerLines(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    /** @return array<string,string> */
    private static function parseHeaderText(string $headerText): array
    {
        $blocks = preg_split("/\r?\n\r?\n/", trim($headerText)) ?: [];
        $last = '';

        foreach ($blocks as $block) {
            if (preg_match('/^HTTP\/\S+\s+\d{3}/i', $block) === 1) {
                $last = $block;
            }
        }

        return self::parseHeaderLines(preg_split('/\r?\n/', $last) ?: []);
    }

    /** @param list<string> $lines @return array{0:int,1:array<string,string>} */
    private static function parseStreamHeaders(array $lines): array
    {
        $status = 0;

        foreach ($lines as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $line, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return [$status, self::parseHeaderLines($lines)];
    }

    /** @param list<string> $lines @return array<string,string> */
    private static function parseHeaderLines(array $lines): array
    {
        $headers = [];

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            $headers[$key] = isset($headers[$key]) && $headers[$key] !== ''
                ? $headers[$key] . ', ' . $value
                : $value;
        }

        return $headers;
    }
}
