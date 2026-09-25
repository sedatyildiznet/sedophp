<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use InvalidArgumentException;
use SedoPHP\Cache\Cache;

final class OneTimeToken
{
    /** @param array<string,mixed> $metadata */
    public static function issue(
        string $purpose,
        int|string $subject,
        int $ttlSeconds,
        array $metadata = [],
    ): string {
        self::assertPurpose($purpose);

        if ($ttlSeconds < 1) {
            throw new InvalidArgumentException('One-time token TTL must be at least one second.');
        }

        $token = self::encode(random_bytes(32));

        Cache::put(self::key($purpose, $token), [
            'subject' => $subject,
            'metadata' => $metadata,
        ], $ttlSeconds);

        return $token;
    }

    /** @return array{subject:int|string,metadata:array<string,mixed>}|null */
    public static function peek(string $purpose, string $token): ?array
    {
        self::assertPurpose($purpose);
        $value = Cache::get(self::key($purpose, $token));

        return self::normalize($value);
    }

    /** @return array{subject:int|string,metadata:array<string,mixed>}|null */
    public static function consume(string $purpose, string $token): ?array
    {
        self::assertPurpose($purpose);
        $value = Cache::pull(self::key($purpose, $token));

        return self::normalize($value);
    }

    public static function revoke(string $purpose, string $token): void
    {
        self::assertPurpose($purpose);
        Cache::forget(self::key($purpose, $token));
    }

    private static function key(string $purpose, string $token): string
    {
        return 'auth:one-time:' . $purpose . ':' . hash('sha256', $token);
    }

    private static function assertPurpose(string $purpose): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $purpose) !== 1) {
            throw new InvalidArgumentException("Invalid one-time token purpose: {$purpose}");
        }
    }

    /** @return array{subject:int|string,metadata:array<string,mixed>}|null */
    private static function normalize(mixed $value): ?array
    {
        if (
            !is_array($value)
            || !array_key_exists('subject', $value)
            || (!is_int($value['subject']) && !is_string($value['subject']))
        ) {
            return null;
        }

        $metadata = $value['metadata'] ?? [];

        return [
            'subject' => $value['subject'],
            'metadata' => is_array($metadata) ? $metadata : [],
        ];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
