<?php

declare(strict_types=1);

namespace SedoPHP\Core;

final class RequestContext
{
    private static ?string $requestId = null;

    public static function begin(?string $incoming = null): string
    {
        $incoming = is_string($incoming) ? trim($incoming) : '';

        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1) {
            self::$requestId = $incoming;
            return self::$requestId;
        }

        self::$requestId = bin2hex(random_bytes(16));
        return self::$requestId;
    }

    public static function id(): ?string
    {
        return self::$requestId;
    }

    public static function reset(): void
    {
        self::$requestId = null;
    }
}
