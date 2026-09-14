<?php

declare(strict_types=1);

namespace SedoPHP\Core;

final class Env
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '') {
                continue;
            }

            if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) {
                $value = stripcslashes(substr($value, 1, -1));
            } elseif (($value[0] ?? '') === "'" && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            } else {
                $comment = strpos($value, ' #');
                if ($comment !== false) {
                    $value = rtrim(substr($value, 0, $comment));
                }
            }

            $existing = getenv($key);
            self::$values[$key] = $existing !== false ? self::cast($existing) : self::cast($value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : self::cast($value);
    }

    private static function cast(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
