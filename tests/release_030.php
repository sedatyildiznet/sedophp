<?php

declare(strict_types=1);

use SedoPHP\Cache\Cache;
use SedoPHP\Core\Config;
use SedoPHP\Core\Logger;
use SedoPHP\Core\RequestContext;
use SedoPHP\Database\Database;
use SedoPHP\Filesystem\Filesystem;
use SedoPHP\Http\HttpClient;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\RequestIdMiddleware;
use SedoPHP\Routing\Router;
use SedoPHP\Security\SignedUrl;

[$suite, $test, $expect] = require __DIR__ . '/Support/bootstrap.php';

$cacheDirectory = sys_get_temp_dir() . '/sedophp_release030_cache_' . getmypid();
Cache::configure(['path' => $cacheDirectory, 'prefix' => 'release030_'], dirname(__DIR__));

$test('0.3 keeps zero third-party runtime Composer dependencies', static function () use ($expect): void {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $requires = array_keys((array) ($composer['require'] ?? []));
    sort($requires);

    $expect($requires === ['ext-fileinfo', 'ext-pdo', 'php']);
});

$test('shared-hosting root blocks framework, tools and example internals', static function () use ($expect): void {
    $rules = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');

    foreach (['app', 'bootstrap', 'config', 'database', 'docs', 'examples', 'routes', 'src', 'storage', 'tests', 'tools', 'vendor'] as $directory) {
        $expect(str_contains($rules, $directory), "Root protection does not mention {$directory}.");
    }

    $expect(str_contains($rules, '.env'));
    $expect(str_contains($rules, 'public/$1'));
});

$test('signed URLs reject tampering and expiry', static function () use ($expect): void {
    Config::set('app.key', str_repeat('r', 48));

    $signed = SignedUrl::sign('/release/42?mode=test', '+5 minutes');
    $expect(SignedUrl::validate(Request::fake('GET', $signed)));
    $expect(!SignedUrl::validate(Request::fake('GET', str_replace('/release/42', '/release/43', $signed))));

    $expired = SignedUrl::sign('/release/42', '-1 minute');
    $expect(!SignedUrl::validate(Request::fake('GET', $expired)));
});

$test('request IDs are returned and invalid external IDs are replaced', static function () use ($expect): void {
    RequestContext::reset();

    $router = new Router();
    $router->alias('request_id', RequestIdMiddleware::class);
    $router->middleware('request_id');
    $router->get('/release/request-id', static fn (): Response => Response::json(['ok' => true]));

    $request = Request::fake('GET', '/release/request-id', [], [
        'X-Request-Id' => "bad\nheader",
    ]);
    $response = $router->dispatch($request);
    $id = $response->headers()['X-Request-Id'] ?? '';

    $expect(is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1);
    $expect($request->attribute('request_id') === $id);

    RequestContext::reset();
});

$test('structured logs redact nested credentials', static function () use ($expect): void {
    $file = sys_get_temp_dir() . '/sedophp_release030_' . getmypid() . '.log';
    @unlink($file);

    Logger::configure([
        'path' => $file,
        'format' => 'json',
        'level' => 'debug',
    ]);

    Logger::error('release security check', [
        'password' => 'password-secret',
        'nested' => [
            'authorization' => 'Bearer secret-value',
            'safe' => 'visible',
        ],
    ]);

    $raw = (string) file_get_contents($file);
    $expect(!str_contains($raw, 'password-secret'));
    $expect(!str_contains($raw, 'secret-value'));

    $payload = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR);
    $expect(($payload['context']['password'] ?? null) === '[REDACTED]');
    $expect(($payload['context']['nested']['authorization'] ?? null) === '[REDACTED]');
    $expect(($payload['context']['nested']['safe'] ?? null) === 'visible');

    @unlink($file);
});

$test('filesystem rejects traversal and absolute paths', static function () use ($expect): void {
    $root = sys_get_temp_dir() . '/sedophp_release030_storage_' . getmypid();
    @mkdir($root, 0775, true);

    Filesystem::configure(['path' => $root], dirname(__DIR__));

    foreach (['../escape.txt', '/tmp/escape.txt'] as $path) {
        $blocked = false;
        try {
            storage()->put($path, 'no');
        } catch (RuntimeException) {
            $blocked = true;
        }
        $expect($blocked, "Unsafe storage path was accepted: {$path}");
    }

    @rmdir($root);
});

$test('HTTP client rejects non-http schemes and embedded credentials', static function () use ($expect): void {
    foreach (['file:///etc/passwd', 'https://user:pass@example.com/'] as $url) {
        $blocked = false;
        try {
            (new HttpClient())->get($url);
        } catch (InvalidArgumentException) {
            $blocked = true;
        }
        $expect($blocked, "Unsafe HTTP URL was accepted: {$url}");
    }
});

$test('database diagnostics are opt-in and do not require a logger service', static function () use ($expect): void {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $expect(true);
        return;
    }

    Database::configure([
        'driver' => 'sqlite',
        'sqlite' => ':memory:',
        'log_queries' => false,
        'slow_query_ms' => 0,
    ]);

    $expect(!Database::diagnosticsEnabled());
    Database::pdo()->exec('CREATE TABLE release030 (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
    Database::table('release030')->insert(['id' => 1, 'value' => 'ok']);
    $expect(Database::table('release030')->where('id', 1)->value('value') === 'ok');
});

foreach (glob($cacheDirectory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cacheDirectory);

exit($suite->finish('Release 0.3'));
