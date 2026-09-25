<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use InvalidArgumentException;
use SedoPHP\Database\Database;
use SedoPHP\Security\Csrf;
use SedoPHP\Security\RateLimiter;
use SedoPHP\Session\Session;

final class Auth
{
    /** @var array<string, mixed> */
    private static array $config = [];
    /** @var array<string, mixed>|null|false */
    private static array|null|false $cachedUser = false;

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$cachedUser = false;
    }

    public static function attempt(string $identity, string $password): bool
    {
        $maxAttempts = max(0, (int) (self::$config['login_max_attempts'] ?? 0));
        $decaySeconds = max(1, (int) (self::$config['login_decay_seconds'] ?? 60));
        $throttleKey = self::loginThrottleKey($identity);

        if ($maxAttempts > 0) {
            $limit = RateLimiter::hit($throttleKey, $maxAttempts, $decaySeconds);
            if (!$limit['allowed']) {
                return false;
            }
        }

        $table = (string) (self::$config['table'] ?? 'users');
        $identityColumn = (string) (self::$config['identity'] ?? 'email');
        $passwordColumn = (string) (self::$config['password'] ?? 'password');
        $idColumn = (string) (self::$config['id'] ?? 'id');
        $row = Database::table($table)->where($identityColumn, $identity)->first();

        if ($row === null || !isset($row[$passwordColumn]) || !password_verify($password, (string) $row[$passwordColumn])) {
            return false;
        }

        if (password_needs_rehash((string) $row[$passwordColumn], PASSWORD_DEFAULT)) {
            Database::table($table)->where($idColumn, $row[$idColumn])->update([
                $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        if ($maxAttempts > 0) {
            RateLimiter::clear($throttleKey);
        }

        Session::regenerate();
        Csrf::regenerate();
        Session::set((string) (self::$config['session_key'] ?? '_sedo_auth_id'), $row[$idColumn]);
        self::$cachedUser = self::sanitize($row);
        return true;
    }

    public static function clearLoginAttempts(string $identity): void
    {
        RateLimiter::clear(self::loginThrottleKey($identity));
    }

    public static function resetPassword(int|string $id, string $password): bool
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        $table = (string) (self::$config['table'] ?? 'users');
        $idColumn = (string) (self::$config['id'] ?? 'id');
        $passwordColumn = (string) (self::$config['password'] ?? 'password');

        $updated = Database::table($table)
            ->where($idColumn, $id)
            ->update([$passwordColumn => password_hash($password, PASSWORD_DEFAULT)]);

        return $updated > 0;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): mixed
    {
        return Session::get((string) (self::$config['session_key'] ?? '_sedo_auth_id'));
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== false) {
            return self::$cachedUser;
        }

        $id = self::id();
        if ($id === null) {
            self::$cachedUser = null;
            return null;
        }

        $table = (string) (self::$config['table'] ?? 'users');
        $idColumn = (string) (self::$config['id'] ?? 'id');
        $row = Database::table($table)->where($idColumn, $id)->first();

        if ($row === null) {
            Session::forget((string) (self::$config['session_key'] ?? '_sedo_auth_id'));
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = self::sanitize($row);
        return self::$cachedUser;
    }

    public static function logout(): void
    {
        Session::forget((string) (self::$config['session_key'] ?? '_sedo_auth_id'));
        Session::regenerate();
        Csrf::regenerate();
        self::$cachedUser = false;
    }

    public static function loginPath(): string
    {
        return (string) (self::$config['login_path'] ?? '/login');
    }

    private static function loginThrottleKey(string $identity): string
    {
        return 'auth-login:' . hash('sha256', strtolower(trim($identity)));
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function sanitize(array $row): array
    {
        unset($row[(string) (self::$config['password'] ?? 'password')]);
        return $row;
    }
}
