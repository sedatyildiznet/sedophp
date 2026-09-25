<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use SedoPHP\Core\Config;

final class EmailVerification
{
    private const PURPOSE = 'email-verification';

    /** @param array<string,mixed> $metadata */
    public static function issue(
        int|string $userId,
        array $metadata = [],
        ?int $ttlSeconds = null,
    ): string {
        $ttlSeconds ??= (int) Config::get('auth.email_verification_ttl', 86400);
        return OneTimeToken::issue(self::PURPOSE, $userId, $ttlSeconds, $metadata);
    }

    /** @return array{subject:int|string,metadata:array<string,mixed>}|null */
    public static function verify(string $token): ?array
    {
        return OneTimeToken::consume(self::PURPOSE, $token);
    }

    public static function revoke(string $token): void
    {
        OneTimeToken::revoke(self::PURPOSE, $token);
    }
}
