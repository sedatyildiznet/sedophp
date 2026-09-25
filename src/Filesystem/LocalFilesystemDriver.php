<?php

declare(strict_types=1);

namespace SedoPHP\Filesystem;

use RuntimeException;

final class LocalFilesystemDriver implements FilesystemDriverInterface
{
    public function __construct(private readonly string $root)
    {
        if ($root === '') {
            throw new RuntimeException('Filesystem root cannot be empty.');
        }
    }

    public function put(string $path, string $contents): void
    {
        $full = $this->fullPath($path);
        $directory = dirname($full);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        $temporary = $full . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $contents, LOCK_EX) === false || !@rename($temporary, $full)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to write file: {$path}");
        }
    }

    public function get(string $path): string
    {
        $full = $this->fullPath($path);
        if (!is_file($full)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $contents = @file_get_contents($full);
        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->fullPath($path));
    }

    public function delete(string $path): bool
    {
        $full = $this->fullPath($path);
        return !is_file($full) || @unlink($full);
    }

    public function size(string $path): int
    {
        $full = $this->fullPath($path);
        if (!is_file($full)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $size = @filesize($full);
        if ($size === false) {
            throw new RuntimeException("Unable to determine file size: {$path}");
        }

        return (int) $size;
    }

    public function makeDirectory(string $path): void
    {
        $full = $this->fullPath($path, true);
        if (!is_dir($full) && !mkdir($full, 0775, true) && !is_dir($full)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    /** @return list<string> */
    public function files(string $directory = ''): array
    {
        $full = $directory === '' ? $this->rootPath() : $this->fullPath($directory, true);
        if (!is_dir($full)) {
            return [];
        }

        $items = [];
        foreach (scandir($full) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $candidate = $full . DIRECTORY_SEPARATOR . $item;
            if (is_file($candidate)) {
                $items[] = $item;
            }
        }

        sort($items, SORT_STRING);
        return $items;
    }

    private function fullPath(string $path, bool $allowEmpty = false): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (!$allowEmpty && $path === '') {
            throw new RuntimeException('Filesystem path cannot be empty.');
        }

        if (
            str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
        ) {
            throw new RuntimeException('Filesystem path must be relative.');
        }

        $segments = $path === '' ? [] : explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Filesystem path contains an invalid segment.');
            }
        }

        $relative = implode(DIRECTORY_SEPARATOR, $segments);
        return $this->rootPath() . ($relative === '' ? '' : DIRECTORY_SEPARATOR . $relative);
    }

    private function rootPath(): string
    {
        return rtrim($this->root, '/\\');
    }
}
