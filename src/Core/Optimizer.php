<?php

declare(strict_types=1);

namespace SedoPHP\Core;

use RuntimeException;
use SedoPHP\Routing\Route;
use SedoPHP\Routing\Router;

final class Optimizer
{
    /**
     * @param array<string,array<string,mixed>> $config
     * @param list<Route> $routes
     * @return list<string>
     */
    public static function build(string $basePath, array $config, array $routes): array
    {
        self::assertCacheable($config, 'config');

        $directory = self::directory($basePath);
        self::ensureDirectory($directory);

        $routeManifest = array_map(
            static fn (Route $route): array => [
                'method' => $route->method,
                'path' => $route->pattern,
                'name' => $route->routeName(),
                'action' => Router::describeAction($route->action),
                'middleware' => $route->middlewareNames(),
            ],
            $routes
        );

        $configFile = $directory . DIRECTORY_SEPARATOR . 'config.php';
        $routesFile = $directory . DIRECTORY_SEPARATOR . 'routes.php';

        self::writePhpArray($configFile, $config);
        self::writePhpArray($routesFile, $routeManifest);

        return [$configFile, $routesFile];
    }

    public static function clear(string $basePath): int
    {
        $directory = self::directory($basePath);
        $count = 0;

        foreach (['config.php', 'routes.php'] as $name) {
            $file = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public static function configFile(string $basePath): string
    {
        return self::directory($basePath) . DIRECTORY_SEPARATOR . 'config.php';
    }

    public static function routesFile(string $basePath): string
    {
        return self::directory($basePath) . DIRECTORY_SEPARATOR . 'routes.php';
    }

    private static function directory(string $basePath): string
    {
        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create optimization cache directory: {$directory}");
        }
    }

    /** @param array<mixed> $value */
    private static function writePhpArray(string $file, array $value): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($value, true) . ";\n";
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporary, $content, LOCK_EX) === false || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to write optimization cache: {$file}");
        }
    }

    private static function assertCacheable(mixed $value, string $path): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (!is_array($value)) {
            throw new RuntimeException("Configuration value cannot be cached: {$path}");
        }

        foreach ($value as $key => $item) {
            self::assertCacheable($item, $path . '.' . (string) $key);
        }
    }
}
