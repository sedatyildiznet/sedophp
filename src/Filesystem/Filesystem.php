<?php

declare(strict_types=1);

namespace SedoPHP\Filesystem;

use RuntimeException;

final class Filesystem
{
    private static ?FilesystemDriverInterface $driver = null;

    /** @param array<string,mixed> $config */
    public static function configure(array $config, string $basePath): void
    {
        $path = (string) ($config['path'] ?? 'storage/app');
        $isAbsolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        $root = $isAbsolute
            ? rtrim($path, '/\\')
            : rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');

        self::$driver = new LocalFilesystemDriver($root);
    }

    public static function useDriver(FilesystemDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    public static function driver(): FilesystemDriverInterface
    {
        return self::$driver ?? throw new RuntimeException('Filesystem has not been configured.');
    }
}
