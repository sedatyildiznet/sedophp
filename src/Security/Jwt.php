<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use RuntimeException;
use SedoPHP\Cache\Cache;

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

    /** @param array<string,mixed> $claims @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int} */
    public static function pair(array $claims, int $accessTtl = 3600, int $refreshTtl = 2592000): array
    {
        $subject = $claims['sub'] ?? null;
        $accessClaims = array_merge($claims, ['typ' => 'access', 'jti' => bin2hex(random_bytes(16))]);
        $refreshClaims = ['typ' => 'refresh', 'jti' => bin2hex(random_bytes(16))];
        if ($subject !== null) {
            $refreshClaims['sub'] = $subject;
        }
        return [
            'access_token' => self::encode($accessClaims, $accessTtl),
            'refresh_token' => self::encode($refreshClaims, $refreshTtl),
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
        ];
    }

    /** @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int}|null */
    public static function refresh(string $refreshToken, int $accessTtl = 3600, int $refreshTtl = 2592000): ?array
    {
        $claims = self::decode($refreshToken);
        if ($claims === null || ($claims['typ'] ?? null) !== 'refresh') {
            return null;
        }
        self::revoke($refreshToken);
        return self::pair(['sub' => $claims['sub'] ?? null], $accessTtl, $refreshTtl);
    }

    public static function revoke(string $token): bool
    {
        $claims = self::decode($token);
        if ($claims === null || !isset($claims['jti'], $claims['exp'])) {
            return false;
        }
        Cache::put('jwt:revoked:' . (string) $claims['jti'], true, max(1, (int) $claims['exp'] - time()));
        return true;
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
        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || (int) $payload['exp'] <= $now) {
            return null;
        }

        if (isset($payload['nbf']) && (!is_numeric($payload['nbf']) || (int) $payload['nbf'] > $now)) {
            return null;
        }

        if (self::$issuer !== '' && (!isset($payload['iss']) || !hash_equals(self::$issuer, (string) $payload['iss']))) {
            return null;
        }

        if (isset($payload['jti'])) {
            try {
                if (Cache::has('jwt:revoked:' . (string) $payload['jti'])) {
                    return null;
                }
            } catch (RuntimeException) {
                // Signature/claim validation remains usable before the optional cache is booted.
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
