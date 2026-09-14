<?php

declare(strict_types=1);

namespace SedoPHP\Session;

final class Session
{
    /** @var array<string, mixed> */
    private static array $config = [];

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');

        $secure = (bool) (self::$config['secure'] ?? false)
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_name((string) (self::$config['name'] ?? 'sedophp_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (string) (self::$config['path'] ?? '/'),
            'secure' => $secure,
            'httponly' => (bool) (self::$config['http_only'] ?? true),
            'samesite' => (string) (self::$config['same_site'] ?? 'Lax'),
        ]);
        session_start();

        $old = $_SESSION['_sedo_flash_old'] ?? [];
        if (is_array($old)) {
            foreach ($old as $key) {
                unset($_SESSION[(string) $key]);
            }
        }
        $_SESSION['_sedo_flash_old'] = $_SESSION['_sedo_flash_new'] ?? [];
        $_SESSION['_sedo_flash_new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function flash(string $key, mixed $value): void
    {
        self::set($key, $value);
        $_SESSION['_sedo_flash_new'] ??= [];
        if (!in_array($key, $_SESSION['_sedo_flash_new'], true)) {
            $_SESSION['_sedo_flash_new'][] = $key;
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        session_destroy();
    }
}
