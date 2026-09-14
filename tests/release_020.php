<?php

declare(strict_types=1);

use SedoPHP\Database\Blueprint;
use SedoPHP\Database\Database;
use SedoPHP\Database\Model;
use SedoPHP\Database\Schema;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\CorsMiddleware;
use SedoPHP\Middleware\SecurityHeadersMiddleware;
use SedoPHP\Routing\Router;
use SedoPHP\Security\HttpSecurity;
use SedoPHP\Validation\Validator;

[$suite, $test, $expect] = require __DIR__ . '/Support/bootstrap.php';

$driver = (string) (getenv('TEST_DB_DRIVER') ?: 'sqlite');

if ($driver === 'mysql' && in_array('mysql', PDO::getAvailableDrivers(), true)) {
    Database::configure([
        'driver' => 'mysql',
        'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
        'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
        'database' => (string) (getenv('TEST_DB_NAME') ?: 'sedophp'),
        'username' => (string) (getenv('TEST_DB_USER') ?: 'root'),
        'password' => (string) (getenv('TEST_DB_PASS') ?: ''),
        'charset' => 'utf8mb4',
    ]);
} elseif ($driver === 'sqlite' && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
} else {
    echo "[SKIP] Release 0.2 tests: requested PDO driver is unavailable.\n";
    exit(0);
}

Schema::dropIfExists('sedo_release_posts');
Schema::dropIfExists('sedo_release_users');

Schema::create('sedo_release_users', static function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->boolean('active')->defaultValue(true);
    $table->json('meta')->nullable();
    $table->timestamps();
});

Schema::create('sedo_release_posts', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id');
    $table->string('title');
    $table->boolean('published')->defaultValue(false);
    $table->index('user_id');
});

final class Release020User extends Model
{
    protected string $table = 'sedo_release_users';
    protected array $fillable = ['name', 'active', 'meta'];
    protected array $casts = [
        'active' => 'boolean',
        'meta' => 'array',
    ];
    protected bool $timestamps = true;
}

$test('route groups compose prefixes and middleware', static function () use ($expect): void {
    $router = new Router();

    $router->alias('group-mark', static function (Request $request, callable $next): Response {
        return $next()->withHeader('X-Group-Middleware', 'yes');
    });
    $router->alias('global-mark', static function (Request $request, callable $next): Response {
        return $next()->withHeader('X-Global-Middleware', 'yes');
    });
    $router->middleware('global-mark');

    $router->group(['prefix' => '/api', 'middleware' => 'group-mark'], static function () use ($router): void {
        $router->group(['prefix' => 'v1'], static function () use ($router): void {
            $router->get('/ping', static fn (): array => ['ok' => true]);
        });
    });

    $response = $router->dispatch(Request::fake('GET', '/api/v1/ping'));

    $expect($response->status() === 200);
    $expect($response->body() === '{"ok":true}');
    $expect(($response->headers()['X-Group-Middleware'] ?? null) === 'yes');
    $expect(($response->headers()['X-Global-Middleware'] ?? null) === 'yes');
});

$test('CORS and security middleware cover normal and preflight responses', static function () use ($expect): void {
    HttpSecurity::configure([
        'cors_origins' => ['https://app.example.com'],
        'cors_methods' => ['GET', 'POST', 'OPTIONS'],
        'cors_headers' => ['Authorization', 'Content-Type'],
        'cors_expose_headers' => ['X-Request-Id'],
        'cors_credentials' => true,
        'cors_max_age' => 600,
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],
    ]);

    $router = new Router();
    $router->alias('cors', CorsMiddleware::class);
    $router->alias('security', SecurityHeadersMiddleware::class);
    $router->middleware('cors', 'security');
    $router->get('/api/ping', static fn (): array => ['ok' => true]);

    $headers = ['Origin' => 'https://app.example.com'];
    $response = $router->dispatch(Request::fake('GET', '/api/ping', [], $headers));
    $preflight = $router->dispatch(Request::fake('OPTIONS', '/api/ping', [], $headers));

    $expect(($response->headers()['Access-Control-Allow-Origin'] ?? null) === 'https://app.example.com');
    $expect(($response->headers()['Access-Control-Allow-Credentials'] ?? null) === 'true');
    $expect(($response->headers()['X-Content-Type-Options'] ?? null) === 'nosniff');
    $expect($preflight->status() === 204);
    $expect(($preflight->headers()['Access-Control-Max-Age'] ?? null) === '600');
});

$test('nested validation supports dot paths and wildcard arrays', static function () use ($expect): void {
    $errors = Validator::validate([
        'user' => [
            'email' => 'not-an-email',
        ],
        'items' => [
            [
                'name' => '',
                'password' => 'secret',
                'password_confirmation' => 'secret',
                'mirror' => '',
            ],
            [
                'name' => 'Second',
                'password' => 'secret2',
                'password_confirmation' => 'wrong',
                'mirror' => 'Second',
            ],
        ],
    ], [
        'user.email' => 'required|email',
        'items.*.name' => 'required|string',
        'items.*.password' => 'required|confirmed',
        'items.*.mirror' => 'same:items.*.name',
    ]);

    $expect(isset($errors['user.email']));
    $expect(isset($errors['items.0.name']));
    $expect(!isset($errors['items.0.password']));
    $expect(isset($errors['items.1.password']));
    $expect(isset($errors['items.0.mirror']));
    $expect(!isset($errors['items.1.mirror']));
});

$test('schema builder creates and alters portable tables', static function () use ($expect): void {
    $expect(Schema::hasTable('sedo_release_users'));
    $expect(Schema::hasColumn('sedo_release_users', 'meta'));

    Schema::table('sedo_release_users', static function (Blueprint $table): void {
        $table->string('nickname', 80)->nullable();
    });

    $expect(Schema::hasColumn('sedo_release_users', 'nickname'));
});

$test('model casts and timestamps serialize cleanly', static function () use ($expect): void {
    $user = Release020User::create([
        'name' => 'Cast User',
        'active' => '0',
        'meta' => ['role' => 'admin', 'flags' => [1, 2]],
    ]);

    $expect($user->get('active') === false);
    $expect($user->get('meta')['role'] === 'admin');
    $expect(is_string($user->get('created_at')));
    $expect(is_string($user->get('updated_at')));

    $storedMeta = Database::table('sedo_release_users')->where('id', $user->getKey())->value('meta');
    $expect(is_string($storedMeta) && str_contains($storedMeta, '"role":"admin"'));

    $user->update(['active' => true, 'meta' => ['role' => 'editor']]);
    $fresh = Release020User::find($user->getKey());

    $expect($fresh?->get('active') === true);
    $expect(($fresh?->get('meta')['role'] ?? null) === 'editor');
    $expect(is_array($fresh?->toArray()));
});

$test('query builder supports joins grouping having and grouped pagination totals', static function () use ($expect): void {
    $first = Release020User::create(['name' => 'One', 'active' => true, 'meta' => []]);
    $second = Release020User::create(['name' => 'Two', 'active' => false, 'meta' => []]);

    Database::table('sedo_release_posts')->insert([
        'user_id' => $first->getKey(),
        'title' => 'First post',
        'published' => 1,
    ]);
    Database::table('sedo_release_posts')->insert([
        'user_id' => $first->getKey(),
        'title' => 'Second post',
        'published' => 0,
    ]);

    $joined = Database::table('sedo_release_users')
        ->select('sedo_release_users.name', 'sedo_release_posts.title')
        ->leftJoin('sedo_release_posts', 'sedo_release_users.id', '=', 'sedo_release_posts.user_id')
        ->where('sedo_release_users.id', $first->getKey())
        ->orderBy('sedo_release_posts.id')
        ->get();

    $expect(count($joined) === 2);
    $expect(($joined[0]['name'] ?? null) === 'One');

    $grouped = Database::table('sedo_release_users')
        ->select('sedo_release_users.active')
        ->groupBy('sedo_release_users.active')
        ->having('sedo_release_users.active', 1)
        ->get();

    $expect(count($grouped) === 1);
    $expect((int) ($grouped[0]['active'] ?? 0) === 1);

    $page = Database::table('sedo_release_users')
        ->select('sedo_release_users.active')
        ->groupBy('sedo_release_users.active')
        ->paginate(1, 1);

    $expect($page['total'] === 2);
    $expect($page['last_page'] === 2);

    $expect(Release020User::find($second->getKey())?->get('name') === 'Two');
});

Schema::dropIfExists('sedo_release_posts');
Schema::dropIfExists('sedo_release_users');

exit($suite->finish('Release 0.2'));
