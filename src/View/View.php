<?php

declare(strict_types=1);

namespace SedoPHP\View;

use RuntimeException;
use SedoPHP\Http\Response;

final class View
{
    private static string $directory = '';

    public static function configure(string $directory): void
    {
        self::$directory = rtrim($directory, '/');
    }

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], int $status = 200): Response
    {
        if (!preg_match('/^[A-Za-z0-9_.\/-]+$/', $view) || str_contains($view, '..')) {
            throw new RuntimeException('Invalid view name.');
        }

        $relative = str_replace('.', '/', trim($view, '/')) . '.php';
        $file = self::$directory . '/' . $relative;

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }

        $content = (static function (string $__sedoFile, array $__sedoData): string {
            extract($__sedoData, EXTR_SKIP);
            ob_start();
            require $__sedoFile;
            return (string) ob_get_clean();
        })($file, $data);

        return new Response($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
