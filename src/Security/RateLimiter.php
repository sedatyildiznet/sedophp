<?php

declare(strict_types=1);

namespace SedoPHP\Security;

use SedoPHP\Cache\Cache;

final class RateLimiter
{
    /** @return array{allowed:bool,remaining:int,retry_after:int,attempts:int} */
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $maxAttempts = max(1, $maxAttempts);
        $decaySeconds = max(1, $decaySeconds);
        $attempts = Cache::increment('rate:' . $key, 1, $decaySeconds);

        return [
            'allowed' => $attempts <= $maxAttempts,
            'remaining' => max(0, $maxAttempts - $attempts),
            'retry_after' => $decaySeconds,
            'attempts' => $attempts,
        ];
    }

    public static function clear(string $key): void
    {
        Cache::forget('rate:' . $key);
    }
}
