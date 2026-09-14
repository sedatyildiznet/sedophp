<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use RuntimeException;

final class Jwt
{
    private static string $secret = '';
    private static string $issuer = '';

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$secret = (string) ($config['jwt_secret'] ?? '');
        self::$issuer = (string) ($config['jwt_issuer'] ?? '');
    }

    /** @param array<string, mixed> $claims */
    public static function encode(array $claims, int $ttlSeconds = 3600): string
    {
        if (strlen(self::$secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be configured with at least 32 characters.');
        }

        $now = time();
        $claims['iat'] ??= $now;
        $claims['exp'] ??= $now + $ttlSeconds;

        if (self::$issuer !== '') {
            $claims['iss'] ??= self::$issuer;
        }

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), self::$secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /** @return array<string, mixed>|null */
    public static function decode(string $token): ?array
    {
        if (strlen(self::$secret) < 32) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerPart, $payloadPart, $signaturePart] = $parts;
        $headerJson = self::base64UrlDecode($headerPart);
        $payloadJson = self::base64UrlDecode($payloadPart);
        $signature = self::base64UrlDecode($signaturePart);

        if ($headerJson === null || $payloadJson === null || $signature === null) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = hash_hmac('sha256', $headerPart . '.' . $payloadPart, self::$secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $now = time();

        if (array_key_exists('exp', $payload)) {
            if (!is_numeric($payload['exp']) || (int) $payload['exp'] <= $now) {
                return null;
            }
        }

        if (array_key_exists('nbf', $payload)) {
            if (!is_numeric($payload['nbf']) || (int) $payload['nbf'] > $now) {
                return null;
            }
        }

        if (self::$issuer !== '') {
            if (!isset($payload['iss']) || !is_string($payload['iss']) || !hash_equals(self::$issuer, $payload['iss'])) {
                return null;
            }
        }

        return $payload;
    }

    public static function bearerToken(?string $authorization): ?string
    {
        if ($authorization === null || preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
