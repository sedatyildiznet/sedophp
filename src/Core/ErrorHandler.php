<?php

declare(strict_types=1);

namespace SedoPHP\Core;

use ErrorException;
use SedoPHP\Http\Response;
use Throwable;

final class ErrorHandler
{
    public static function register(bool $debug): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception) use ($debug): void {
            Logger::error($exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $debug
                    ? sprintf("%s: %s in %s:%d\n%s\n", $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString())
                    : "SedoPHP error. Check storage/logs/app.log.\n");
                return;
            }

            $body = $debug ? self::debugPage($exception) : self::productionPage();
            (new Response($body, 500, ['Content-Type' => 'text/html; charset=UTF-8']))->send();
        });
    }

    private static function debugPage(Throwable $exception): string
    {
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $file = htmlspecialchars($exception->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>SedoPHP Error</title>
<style>body{font:15px/1.55 ui-monospace,monospace;max-width:1100px;margin:40px auto;padding:0 24px;color:#161616}pre{white-space:pre-wrap;background:#f5f5f5;padding:20px;border-radius:8px;overflow:auto}h1{font-family:system-ui,sans-serif}</style></head>
<body><h1>SedoPHP Error</h1><p><strong>{$message}</strong></p><p>{$file}:{$exception->getLine()}</p><pre>{$trace}</pre></body></html>
HTML;
    }

    private static function productionPage(): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Server Error</title></head><body><h1>500</h1><p>Something went wrong.</p></body></html>';
    }
}
