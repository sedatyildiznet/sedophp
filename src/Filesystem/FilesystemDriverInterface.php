<?php

declare(strict_types=1);

namespace SedoPHP\Filesystem;

interface FilesystemDriverInterface
{
    public function put(string $path, string $contents): void;
    public function get(string $path): string;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function size(string $path): int;
    public function makeDirectory(string $path): void;

    /** @return list<string> */
    public function files(string $directory = ''): array;
}
