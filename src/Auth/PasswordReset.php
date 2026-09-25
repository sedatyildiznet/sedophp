<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use SedoPHP\Core\Config;

final class PasswordReset
{
    private const PURPOSE = 'password-reset';

    public static function issue(int|string $userId, ?int $ttlSeconds = null): string
    {
        $ttlSeconds ??= (int) Config::get('auth.password_reset_ttl', 3600);
        return OneTimeToken::issue(self::PURPOSE, $userId, $ttlSeconds);
    }

    public static function reset(string $token, string $newPassword): bool
    {
        $payload = OneTimeToken::consume(self::PURPOSE, $token);
        if ($payload === null) {
            return false;
        }

        return Auth::resetPassword($payload['subject'], $newPassword);
    }

    public static function revoke(string $token): void
    {
        OneTimeToken::revoke(self::PURPOSE, $token);
    }
}
