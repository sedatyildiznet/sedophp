<?php

declare(strict_types=1);

namespace SedoPHP\Core;

final class Logger
{
    private static ?string $file = null;

    public static function configure(string $file): void
    {
        self::$file = $file;
    }

    /** @param array<string, mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if (self::$file === null) {
            return;
        }

        $directory = dirname(self::$file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $contextText = $context === []
            ? ''
            : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $line = sprintf("[%s] %s: %s%s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $contextText);
        @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }
}
