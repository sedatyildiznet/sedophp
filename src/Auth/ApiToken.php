<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use DateTimeInterface;
use SedoPHP\Core\Config;
use SedoPHP\Database\Database;
use SedoPHP\Http\Request;
use SedoPHP\Security\Jwt;

final class ApiToken
{
    /** @param list<string> $abilities */
    public static function issue(
        int|string $userId,
        string $name = 'default',
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): string {
        $plain = 'sedo_' . bin2hex(random_bytes(32));

        Database::table(self::table())->insert([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'abilities' => json_encode(array_values($abilities), JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'last_used_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $plain;
    }

    /** @return array<string, mixed>|null */
    public static function authenticate(Request $request): ?array
    {
        $plain = Jwt::bearerToken((string) $request->header('authorization', ''));
        if ($plain === null || !str_starts_with($plain, 'sedo_')) {
            return null;
        }

        $row = Database::table(self::table())
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        if ($row === null) {
            return null;
        }

        if (
            isset($row['expires_at'])
            && $row['expires_at'] !== null
            && strtotime((string) $row['expires_at']) <= time()
        ) {
            return null;
        }

        $userId = $row['user_id'] ?? null;
        if ($userId === null) {
            Database::table(self::table())->where('id', $row['id'])->delete();
            return null;
        }

        $userExists = Database::table((string) Config::get('auth.table', 'users'))
            ->where((string) Config::get('auth.id', 'id'), $userId)
            ->exists();

        if (!$userExists) {
            Database::table(self::table())->where('id', $row['id'])->delete();
            return null;
        }

        Database::table(self::table())
            ->where('id', $row['id'])
            ->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);

        $abilities = json_decode((string) ($row['abilities'] ?? '[]'), true);
        $row['abilities'] = is_array($abilities) ? array_values($abilities) : [];
        unset($row['token_hash']);

        return $row;
    }

    /** @param array<string, mixed> $token */
    public static function can(array $token, string $ability): bool
    {
        $abilities = $token['abilities'] ?? [];
        return is_array($abilities)
            && (in_array('*', $abilities, true) || in_array($ability, $abilities, true));
    }

    public static function revoke(string $plainToken): bool
    {
        return Database::table(self::table())
            ->where('token_hash', hash('sha256', $plainToken))
            ->delete() > 0;
    }

    public static function revokeAllForUser(int|string $userId): int
    {
        return Database::table(self::table())
            ->where('user_id', $userId)
            ->delete();
    }

    private static function table(): string
    {
        return (string) Config::get('auth.api_token_table', 'api_tokens');
    }
}
