<?php

declare(strict_types=1);

namespace SedoPHP\Testing;

use JsonException;
use RuntimeException;
use SedoPHP\Http\Response;

final class TestResponse
{
    public function __construct(private readonly Response $response)
    {
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function assertStatus(int $expected): self
    {
        if ($this->response->status() !== $expected) {
            throw new RuntimeException(
                "Expected HTTP status {$expected}, got {$this->response->status()}."
            );
        }

        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        $headers = [];
        foreach ($this->response->headers() as $header => $headerValue) {
            $headers[strtolower($header)] = $headerValue;
        }

        $key = strtolower($name);
        if (!array_key_exists($key, $headers)) {
            throw new RuntimeException("Expected response header is missing: {$name}");
        }

        if ($value !== null && $headers[$key] !== $value) {
            throw new RuntimeException(
                "Expected response header {$name} to be {$value}, got {$headers[$key]}."
            );
        }

        return $this;
    }

    public function assertSee(string $text): self
    {
        if (!str_contains($this->response->body(), $text)) {
            throw new RuntimeException("Response body does not contain expected text: {$text}");
        }

        return $this;
    }

    /** @param array<string,mixed> $expected */
    public function assertJson(array $expected): self
    {
        $actual = $this->json();

        if (!$this->contains($actual, $expected)) {
            throw new RuntimeException('Response JSON does not contain the expected structure.');
        }

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        $value = $this->json();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new RuntimeException("Response JSON path not found: {$path}");
            }
            $value = $value[$segment];
        }

        if ($value !== $expected) {
            throw new RuntimeException("Response JSON path {$path} did not match the expected value.");
        }

        return $this;
    }

    public function assertRedirect(?string $location = null, int $status = 302): self
    {
        $this->assertStatus($status);
        $this->assertHeader('Location');

        if ($location !== null) {
            $this->assertHeader('Location', $location);
        }

        return $this;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        try {
            $decoded = json_decode($this->response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Response body is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Response JSON must decode to an array or object.');
        }

        return $decoded;
    }

    /** @param array<mixed> $actual @param array<mixed> $expected */
    private function contains(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value)) {
                if (!is_array($actual[$key]) || !$this->contains($actual[$key], $value)) {
                    return false;
                }
                continue;
            }

            if ($actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
