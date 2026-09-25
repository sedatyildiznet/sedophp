<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use SedoPHP\Core\Config;
use SedoPHP\Http\Request;

final class SignedUrl
{
    public static function sign(string $uri, DateTimeInterface|string|null $expiresAt = null): string
    {
        [$path, $query] = self::split($uri);
        unset($query['signature']);

        if ($expiresAt !== null) {
            $expires = is_string($expiresAt)
                ? new DateTimeImmutable($expiresAt)
                : $expiresAt;
            $query['expires'] = $expires->getTimestamp();
        }

        $query = self::sort($query);
        $signature = hash_hmac('sha256', self::payload($path, $query), self::key());
        $query['signature'] = $signature;

        return $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function validate(Request $request): bool
    {
        $query = $request->query();
        if (!is_array($query)) {
            return false;
        }

        $signature = $query['signature'] ?? null;
        if (!is_string($signature) || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            return false;
        }

        unset($query['signature']);

        if (isset($query['expires'])) {
            if (!is_scalar($query['expires']) || !ctype_digit((string) $query['expires'])) {
                return false;
            }

            if ((int) $query['expires'] < time()) {
                return false;
            }
        }

        $query = self::sort($query);
        $expected = hash_hmac('sha256', self::payload($request->path(), $query), self::key());

        return hash_equals($expected, $signature);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function split(string $uri): array
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new RuntimeException('Invalid URL to sign.');
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = '/' . ltrim($path, '/');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        return [$path, $query];
    }

    /** @param array<string,mixed> $query */
    private static function payload(string $path, array $query): string
    {
        $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $path . ($encoded === '' ? '' : '?' . $encoded);
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private static function sort(array $value): array
    {
        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sort($item);
            }
        }

        return $value;
    }

    private static function key(): string
    {
        $key = (string) Config::get('app.key', '');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for signed URLs.');
        }

        return $key;
    }
}
