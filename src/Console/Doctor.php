<?php

declare(strict_types=1);

namespace SedoPHP\Console;

use PDO;
use SedoPHP\Core\Application;
use SedoPHP\Core\Config;
use SedoPHP\Core\Optimizer;
use SedoPHP\Database\Database;
use Throwable;

final class Doctor
{
    /**
     * @return list<array{status:string,label:string,detail:string}>
     */
    public static function checks(Application $app): array
    {
        $checks = [];

        self::add(
            $checks,
            version_compare(PHP_VERSION, '8.3.0', '>=') ? 'pass' : 'fail',
            'PHP >= 8.3',
            PHP_VERSION
        );

        self::add(
            $checks,
            extension_loaded('pdo') ? 'pass' : 'fail',
            'PDO extension',
            extension_loaded('pdo') ? 'loaded' : 'missing'
        );

        self::add(
            $checks,
            extension_loaded('fileinfo') ? 'pass' : 'fail',
            'Fileinfo extension',
            extension_loaded('fileinfo') ? 'loaded' : 'missing'
        );

        $driver = (string) Config::get('database.driver', 'mysql');
        $pdoDrivers = PDO::getAvailableDrivers();
        self::add(
            $checks,
            in_array($driver, $pdoDrivers, true) ? 'pass' : 'fail',
            "PDO driver: {$driver}",
            implode(', ', $pdoDrivers) ?: 'none'
        );

        $envFile = $app->path('.env');
        self::add(
            $checks,
            is_file($envFile) ? 'pass' : 'warn',
            '.env file',
            is_file($envFile) ? 'found' : 'missing; environment variables may still be used'
        );

        self::directoryCheck($checks, 'storage/logs writable', $app->path('storage/logs'));
        self::directoryCheck($checks, 'storage/cache writable', $app->path('storage/cache'));

        $filesystemRoot = self::resolvePath(
            $app,
            (string) Config::get('filesystem.path', 'storage/app')
        );
        if (is_dir($filesystemRoot)) {
            self::add(
                $checks,
                is_writable($filesystemRoot) ? 'pass' : 'fail',
                'filesystem storage',
                $filesystemRoot
            );
        } else {
            $parent = dirname($filesystemRoot);
            self::add(
                $checks,
                is_dir($parent) && is_writable($parent) ? 'pass' : 'warn',
                'filesystem storage',
                is_dir($parent) && is_writable($parent)
                    ? 'will be created on first write'
                    : 'path does not exist and parent may not be writable'
            );
        }

        $environment = (string) Config::get('app.env', 'production');
        $debug = (bool) Config::get('app.debug', false);
        self::add(
            $checks,
            $environment === 'production' && $debug ? 'warn' : 'pass',
            'application mode',
            "env={$environment}, debug=" . ($debug ? 'true' : 'false')
        );

        $appKey = (string) Config::get('app.key', '');
        $keyStatus = $appKey === ''
            ? 'warn'
            : (strlen($appKey) < 32 ? 'warn' : 'pass');
        $keyDetail = $appKey === ''
            ? 'missing; signed URLs require APP_KEY'
            : (strlen($appKey) < 32 ? 'configured but shorter than 32 characters' : 'configured');
        self::add($checks, $keyStatus, 'APP_KEY', $keyDetail);

        $appUrl = (string) Config::get('app.url', '');
        $sessionSecure = (bool) Config::get('session.secure', false);
        if (str_starts_with(strtolower($appUrl), 'https://') && !$sessionSecure) {
            self::add(
                $checks,
                'warn',
                'secure session cookie',
                'APP_URL uses HTTPS but SESSION_SECURE is false'
            );
        } else {
            self::add(
                $checks,
                'pass',
                'secure session cookie',
                $sessionSecure ? 'enabled' : 'not required by current APP_URL'
            );
        }

        if ($environment === 'production' && (bool) Config::get('database.log_queries', false)) {
            self::add(
                $checks,
                'warn',
                'query diagnostics',
                'DB_LOG_QUERIES is enabled in production'
            );
        } else {
            self::add(
                $checks,
                'pass',
                'query diagnostics',
                (bool) Config::get('database.log_queries', false) ? 'enabled' : 'disabled'
            );
        }

        self::add(
            $checks,
            is_file($app->path('public/index.php')) ? 'pass' : 'fail',
            'public entrypoint',
            $app->path('public/index.php')
        );

        self::add(
            $checks,
            is_file($app->path('.htaccess')) && is_file($app->path('public/.htaccess')) ? 'pass' : 'fail',
            'shared-hosting rules',
            'root and public .htaccess'
        );

        $rewrite = 'unknown outside Apache';
        $rewriteStatus = 'info';
        if (function_exists('apache_get_modules')) {
            $rewriteLoaded = in_array('mod_rewrite', apache_get_modules(), true);
            $rewriteStatus = $rewriteLoaded ? 'pass' : 'fail';
            $rewrite = $rewriteLoaded ? 'mod_rewrite loaded' : 'mod_rewrite missing';
        }
        self::add($checks, $rewriteStatus, 'Apache rewrite', $rewrite);

        $dbOk = false;
        $dbMessage = 'not checked';
        try {
            Database::pdo()->query('SELECT 1');
            $dbOk = true;
            $dbMessage = 'connection successful';
        } catch (Throwable $exception) {
            $dbMessage = self::safeMessage($exception->getMessage());
        }
        self::add($checks, $dbOk ? 'pass' : 'fail', 'database connection', $dbMessage);

        if ($dbOk) {
            try {
                Database::table('jobs')->select('id')->limit(1)->get();
                self::add($checks, 'pass', 'queue storage', 'jobs table available');
            } catch (Throwable) {
                self::add(
                    $checks,
                    'warn',
                    'queue storage',
                    'jobs table unavailable; run migrations if queueing is needed'
                );
            }
        }

        self::add(
            $checks,
            is_file($app->path('routes/schedule.php')) ? 'pass' : 'warn',
            'scheduler definition',
            is_file($app->path('routes/schedule.php'))
                ? 'routes/schedule.php found'
                : 'routes/schedule.php missing'
        );

        $mailDriver = strtolower((string) Config::get('mail.driver', 'log'));
        if ($mailDriver === 'smtp') {
            $host = trim((string) Config::get('mail.host', ''));
            $from = trim((string) Config::get('mail.from_address', ''));
            $mailOk = $host !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
            self::add(
                $checks,
                $mailOk ? 'pass' : 'fail',
                'mail configuration',
                $mailOk ? 'SMTP configured' : 'SMTP requires MAIL_HOST and a valid MAIL_FROM_ADDRESS'
            );
        } else {
            self::add(
                $checks,
                'pass',
                'mail configuration',
                "driver={$mailDriver}"
            );
        }

        $configCache = Optimizer::configFile($app->path());
        $routeCache = Optimizer::routesFile($app->path());
        self::add(
            $checks,
            'info',
            'optimization cache',
            is_file($configCache) || is_file($routeCache)
                ? 'built'
                : 'not built (optional)'
        );

        return $checks;
    }

    public static function run(Application $app): int
    {
        $failed = 0;

        foreach (self::checks($app) as $check) {
            $status = strtoupper($check['status']);
            echo '[' . str_pad($status, 4) . '] '
                . str_pad($check['label'], 28)
                . ' ' . $check['detail'] . PHP_EOL;

            if ($check['status'] === 'fail') {
                $failed++;
            }
        }

        return $failed === 0 ? 0 : 1;
    }

    /**
     * @param list<array{status:string,label:string,detail:string}> $checks
     */
    private static function add(array &$checks, string $status, string $label, string $detail): void
    {
        $checks[] = compact('status', 'label', 'detail');
    }

    /**
     * @param list<array{status:string,label:string,detail:string}> $checks
     */
    private static function directoryCheck(array &$checks, string $label, string $path): void
    {
        self::add(
            $checks,
            is_dir($path) && is_writable($path) ? 'pass' : 'fail',
            $label,
            $path
        );
    }

    private static function resolvePath(Application $app, string $path): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute
            ? rtrim($path, '/\\')
            : $app->path($path);
    }

    private static function safeMessage(string $message): string
    {
        $message = preg_replace('/password=[^;\s]+/i', 'password=[REDACTED]', $message) ?? $message;
        return substr($message, 0, 240);
    }
}
