<?php

declare(strict_types=1);

namespace SedoPHP\Core;

use RuntimeException;

final class Logger
{
    private static ?string $file = null;
    private static string $format = 'text';
    private static string $minimumLevel = 'info';

    /** @var array<string,int> */
    private const LEVELS = [
        'debug' => 10,
        'info' => 20,
        'warning' => 30,
        'error' => 40,
    ];

    /** @param string|array<string,mixed> $config */
    public static function configure(string|array $config, ?string $basePath = null): void
    {
        if (is_string($config)) {
            self::$file = $config;
            self::$format = 'text';
            self::$minimumLevel = 'info';
            return;
        }

        $path = (string) ($config['path'] ?? 'storage/logs/app.log');
        $isAbsolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        self::$file = $isAbsolute || $basePath === null
            ? $path
            : rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        $format = strtolower((string) ($config['format'] ?? 'text'));
        if (!in_array($format, ['text', 'json'], true)) {
            throw new RuntimeException('Log format must be text or json.');
        }
        self::$format = $format;

        $level = strtolower((string) ($config['level'] ?? 'info'));
        if (!array_key_exists($level, self::LEVELS)) {
            throw new RuntimeException('Log level must be debug, info, warning or error.');
        }
        self::$minimumLevel = $level;
    }

    /** @param array<string,mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if (self::$file === null) {
            return;
        }

        $level = strtolower($level);
        if (!array_key_exists($level, self::LEVELS)) {
            $level = 'info';
        }

        if (self::LEVELS[$level] < self::LEVELS[self::$minimumLevel]) {
            return;
        }

        $directory = dirname(self::$file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $requestId = RequestContext::id();
        if ($requestId !== null && !array_key_exists('request_id', $context)) {
            $context['request_id'] = $requestId;
        }

        $context = self::redact($context);
        $timestamp = date(DATE_ATOM);

        if (self::$format === 'json') {
            $line = json_encode([
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
        } else {
            $contextText = $context === []
                ? ''
                : ' ' . json_encode(
                    $context,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );

            $line = sprintf(
                "[%s] %s: %s%s\n",
                $timestamp,
                strtoupper($level),
                $message,
                $contextText
            );
        }

        @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function sensitiveKey(string $key): bool
    {
        return preg_match(
            '/(?:password|passwd|secret|authorization|cookie|token|jwt|api[_-]?key|db[_-]?pass)/i',
            $key
        ) === 1;
    }

    private static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::sensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = self::redact($childValue, (string) $childKey);
        }

        return $redacted;
    }
}
