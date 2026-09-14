<?php

declare(strict_types=1);

namespace SedoPHP\Http;

use RuntimeException;
use SedoPHP\Core\Application;

final class UploadedFile
{
    /** @param array<string, mixed> $file */
    public static function fromArray(array $file): ?self
    {
        if (!isset($file['tmp_name'], $file['name'], $file['error'])) {
            return null;
        }

        if (is_array($file['tmp_name']) || is_array($file['name'])) {
            throw new RuntimeException('Multiple file uploads are not supported by file(). Handle the upload array directly.');
        }

        return new self(
            (string) $file['tmp_name'],
            (string) $file['name'],
            (string) ($file['type'] ?? ''),
            (int) ($file['size'] ?? 0),
            (int) $file['error'],
        );
    }

    public static function fake(string $path, string $originalName, ?string $mime = null): self
    {
        return new self(
            $path,
            $originalName,
            $mime ?? '',
            is_file($path) ? (int) filesize($path) : 0,
            UPLOAD_ERR_OK,
            true,
        );
    }

    public function __construct(
        private readonly string $path,
        private readonly string $originalName,
        private readonly string $clientMime,
        private readonly int $size,
        private readonly int $error = UPLOAD_ERR_OK,
        private readonly bool $test = false,
    ) {
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && is_file($this->path)
            && ($this->test || is_uploaded_file($this->path));
    }

    public function error(): int
    {
        return $this->error;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function extension(): string
    {
        return strtolower((string) pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function mimeType(): string
    {
        if (function_exists('finfo_open') && is_file($this->path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $this->path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return $this->clientMime;
    }

    public function isImage(): bool
    {
        return $this->isValid() && @getimagesize($this->path) !== false;
    }

    /** @param list<string> $allowedMimes */
    public function save(
        string $directory,
        ?string $filename = null,
        array $allowedMimes = [],
        int $maxBytes = 10485760,
    ): string {
        if (!$this->isValid()) {
            throw new RuntimeException('The uploaded file is not valid.');
        }

        if ($this->size > $maxBytes) {
            throw new RuntimeException('The uploaded file exceeds the allowed size.');
        }

        $mime = $this->mimeType();
        if ($allowedMimes !== [] && !in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException("Upload MIME type is not allowed: {$mime}");
        }

        $extension = $this->extension();
        $blocked = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl'];
        if (in_array($extension, $blocked, true)) {
            throw new RuntimeException('Executable upload extensions are not allowed.');
        }

        $directory = self::absoluteDirectory($directory);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create upload directory: {$directory}");
        }

        $filename ??= bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $filename = basename($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException('Invalid upload filename.');
        }

        $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $moved = $this->test ? @rename($this->path, $target) : @move_uploaded_file($this->path, $target);

        if (!$moved) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        return $target;
    }

    private static function absoluteDirectory(string $directory): string
    {
        $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\/]/', $directory) === 1;
        if (str_starts_with($directory, '/') || $isWindowsAbsolute) {
            return $directory;
        }

        return Application::instance()->path($directory);
    }
}
