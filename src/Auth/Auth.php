<?php

declare(strict_types=1);

namespace SedoPHP\Auth;

use SedoPHP\Database\Database;
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

        Session::regenerate();
        Session::set((string) (self::$config['session_key'] ?? '_sedo_auth_id'), $row[$idColumn]);
        self::$cachedUser = $row;
        return true;
    }

    public static function check(): bool
    {
        return self::id() !== null;
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
        self::$cachedUser = Database::table($table)->where($idColumn, $id)->first();

        if (self::$cachedUser === null) {
            Session::forget((string) (self::$config['session_key'] ?? '_sedo_auth_id'));
        }
        return self::$cachedUser;
    }

    public static function logout(): void
    {
        Session::forget((string) (self::$config['session_key'] ?? '_sedo_auth_id'));
        Session::regenerate();
        self::$cachedUser = false;
    }

    public static function loginPath(): string
    {
        return (string) (self::$config['login_path'] ?? '/login');
    }
}
