<?php

declare(strict_types=1);

use SedoPHP\Auth\ApiToken;
use SedoPHP\Cache\Cache;
use SedoPHP\Database\Database;
use SedoPHP\Database\MigrationRunner;
use SedoPHP\Database\Model;
use SedoPHP\Database\Relations\BelongsTo;
use SedoPHP\Database\Relations\HasMany;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Mail\Mailer;
use SedoPHP\Queue\JobInterface;
use SedoPHP\Queue\Queue;
use SedoPHP\Scheduling\Schedule;
use SedoPHP\Security\Jwt;
use SedoPHP\Security\RateLimiter;
use SedoPHP\Security\HttpSecurity;
use SedoPHP\Middleware\JwtMiddleware;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$passed = 0;
$failed = 0;

$test = static function (string $name, callable $callback) use (&$passed, &$failed): void {
    try {
        $callback();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
};

$expect = static function (bool $condition, string $message = 'Expectation failed.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$cacheDirectory = sys_get_temp_dir() . '/sedophp_features_' . getmypid();
Cache::configure(['path' => $cacheDirectory, 'prefix' => 'test_'], dirname(__DIR__));
Jwt::configure([
    'jwt_secret' => str_repeat('s', 48),
    'jwt_issuer' => 'sedophp-tests',
]);
Mailer::configure([
    'driver' => 'log',
    'from_address' => 'tests@example.com',
    'from_name' => 'SedoPHP Tests',
]);

$testDriver = (string) (getenv('TEST_DB_DRIVER') ?: 'sqlite');
if ($testDriver === 'mysql' && in_array('mysql', PDO::getAvailableDrivers(), true)) {
    Database::configure([
        'driver' => 'mysql',
        'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
        'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
        'database' => (string) (getenv('TEST_DB_NAME') ?: 'sedophp'),
        'username' => (string) (getenv('TEST_DB_USER') ?: 'root'),
        'password' => (string) (getenv('TEST_DB_PASS') ?: ''),
        'charset' => 'utf8mb4',
    ]);
    $database = Database::pdo();
    $database->exec('DROP TABLE IF EXISTS posts');
    $database->exec('DROP TABLE IF EXISTS jobs');
    $database->exec('DROP TABLE IF EXISTS api_tokens');
    $database->exec('DROP TABLE IF EXISTS users');
    $database->exec('DROP TABLE IF EXISTS sedo_migrations');
} elseif ($testDriver === 'sqlite' && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
} else {
    echo "[SKIP] Extended features: requested PDO test driver is unavailable.\n";
    exit(0);
}

(new MigrationRunner(dirname(__DIR__) . '/database/migrations'))->migrate();
$pdo = Database::pdo();
if ($testDriver === 'mysql') {
    $pdo->exec('CREATE TABLE posts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} else {
    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');
}

final class FeatureUser extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];

    public function posts(): HasMany
    {
        return $this->hasMany(FeaturePost::class, 'user_id');
    }
}

final class FeaturePost extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(FeatureUser::class, 'user_id');
    }
}

final class FeatureJob implements JobInterface
{
    public static array $handled = [];

    public function handle(array $payload): void
    {
        self::$handled[] = $payload;
    }
}

final class FailingFeatureJob implements JobInterface
{
    public function handle(array $payload): void
    {
        throw new RuntimeException('Expected queue failure');
    }
}

$test('file cache supports TTL, remember and atomic increment', static function () use ($expect): void {
    Cache::put('name', 'Sedat', 60);
    $expect(Cache::get('name') === 'Sedat');

    $calls = 0;
    $value = Cache::remember('remembered', 60, static function () use (&$calls): string {
        $calls++;
        return 'value';
    });
    $again = Cache::remember('remembered', 60, static function () use (&$calls): string {
        $calls++;
        return 'other';
    });

    $expect($value === 'value' && $again === 'value' && $calls === 1);
    $expect(Cache::increment('counter', 1, 60) === 1);
    $expect(Cache::increment('counter', 2, 60) === 3);
    $expect(Cache::add('lock', true, 60));
    $expect(!Cache::add('lock', true, 60));
});

$test('JWT signs, validates and rejects tampering', static function () use ($expect): void {
    $token = Jwt::encode(['sub' => 7], 300);
    $claims = Jwt::decode($token);

    $expect(($claims['sub'] ?? null) === 7);
    $expect(Jwt::decode($token . 'x') === null);

    $encodeRaw = static function (array $claims): string {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = $encode(hash_hmac('sha256', $header . '.' . $payload, str_repeat('s', 48), true));
        return $header . '.' . $payload . '.' . $signature;
    };

    $expect(Jwt::decode($encodeRaw(['sub' => 7, 'iss' => 'sedophp-tests'])) === null, 'JWT without exp was accepted.');
    $expect(Jwt::decode($encodeRaw(['sub' => 7, 'exp' => time() + 60])) === null, 'JWT without issuer was accepted.');
    $middleware = new JwtMiddleware();
    $response = $middleware->handle(
        Request::fake('GET', '/api/private', [], ['Authorization' => 'Bearer invalid']),
        static fn (): Response => Response::json(['ok' => true])
    );
    $expect($response->status() === 401);

    $pair = Jwt::pair(['sub' => 7], 300, 600);
    $expect((Jwt::decode($pair['access_token'])['typ'] ?? null) === 'access');
    $replacement = Jwt::refresh($pair['refresh_token'], 300, 600);
    $expect(is_array($replacement));
    $expect(Jwt::decode($pair['refresh_token']) === null, 'Used refresh token remained valid.');
    $expect(Jwt::revoke($replacement['access_token']));
    $expect(Jwt::decode($replacement['access_token']) === null);
});

$test('security headers and allowed CORS origins are applied', static function () use ($expect): void {
    HttpSecurity::configure([
        'cors_origins' => ['https://app.example.com'],
        'cors_methods' => ['GET', 'POST'],
        'cors_headers' => ['Authorization'],
        'headers' => ['X-Content-Type-Options' => 'nosniff'],
    ]);
    $response = HttpSecurity::apply(new Response('ok'), Request::fake('GET', '/', [], ['Origin' => 'https://app.example.com']));
    $expect(($response->headers()['Access-Control-Allow-Origin'] ?? null) === 'https://app.example.com');
    $expect(($response->headers()['X-Content-Type-Options'] ?? null) === 'nosniff');
});

$test('request only trusts forwarding headers from configured proxies', static function () use ($expect): void {
    Request::configure(['trusted_proxies' => ['10.0.0.0/8']]);
    $spoofed = Request::fake('GET', '/', [], ['CF-Connecting-IP' => '203.0.113.10'], ['REMOTE_ADDR' => '198.51.100.3']);
    $proxied = Request::fake('GET', '/', [], ['CF-Connecting-IP' => '203.0.113.10'], ['REMOTE_ADDR' => '10.1.2.3']);
    $expect($spoofed->ip() === '198.51.100.3');
    $expect($proxied->ip() === '203.0.113.10');
    Request::configure(['trusted_proxies' => []]);
});

$test('rate limiter blocks after the configured maximum', static function () use ($expect): void {
    RateLimiter::clear('feature');
    $expect(RateLimiter::hit('feature', 2, 60)['allowed']);
    $expect(RateLimiter::hit('feature', 2, 60)['allowed']);
    $expect(!RateLimiter::hit('feature', 2, 60)['allowed']);
});

$test('query builder paginate returns data and metadata', static function () use ($expect): void {
    for ($i = 1; $i <= 5; $i++) {
        Database::table('users')->insert([
            'name' => 'User ' . $i,
            'email' => 'user' . $i . '@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);
    }

    $page = Database::table('users')->orderBy('id')->paginate(2, 2);
    $expect($page['total'] === 5);
    $expect($page['current_page'] === 2);
    $expect($page['last_page'] === 3);
    $expect(count($page['data']) === 2);
    $expect($page['from'] === 3 && $page['to'] === 4);
});

$test('hasMany and belongsTo hydrate related models', static function () use ($expect): void {
    $user = FeatureUser::create([
        'name' => 'Relation User',
        'email' => 'relation@example.com',
        'password' => password_hash('password', PASSWORD_DEFAULT),
    ]);
    $post = FeaturePost::create(['user_id' => $user->getKey(), 'title' => 'Hello']);

    $posts = $user->posts()->get();
    $owner = $post->user()->first();

    $expect(count($posts) === 1);
    $expect($posts[0] instanceof FeaturePost);
    $expect($owner instanceof FeatureUser);
    $expect($owner?->get('email') === 'relation@example.com');

    $users = FeatureUser::with('posts');
    $relationUser = array_values(array_filter(
        $users,
        static fn (FeatureUser $item): bool => $item->get('email') === 'relation@example.com'
    ))[0] ?? null;
    $expect($relationUser instanceof FeatureUser);
    $expect(count($relationUser?->get('posts', [])) === 1);
});

$test('database API tokens authenticate bearer requests and abilities', static function () use ($expect): void {
    $userId = Database::table('users')->where('email', 'relation@example.com')->value('id');
    $plain = ApiToken::issue((int) $userId, 'tests', ['posts:read']);
    $request = Request::fake('GET', '/api/posts', [], ['Authorization' => 'Bearer ' . $plain]);
    $token = ApiToken::authenticate($request);

    $expect(is_array($token));
    $expect(ApiToken::can($token, 'posts:read'));
    $expect(!ApiToken::can($token, 'posts:write'));
    $expect(ApiToken::revoke($plain));
});

$test('database API tokens are rejected when their user was deleted', static function () use ($expect): void {
    $user = FeatureUser::create([
        'name' => 'Deleted User',
        'email' => 'deleted@example.com',
        'password' => password_hash('password', PASSWORD_DEFAULT),
    ]);
    $plain = ApiToken::issue((int) $user->getKey());
    $user->delete();
    $request = Request::fake('GET', '/api/private', [], ['Authorization' => 'Bearer ' . $plain]);
    $expect(ApiToken::authenticate($request) === null);
});

$test('database queue dispatches and executes jobs', static function () use ($expect): void {
    FeatureJob::$handled = [];
    Queue::push(FeatureJob::class, ['id' => 99]);
    $processed = Queue::work(5);

    $expect($processed === 1);
    $expect((FeatureJob::$handled[0]['id'] ?? null) === 99);
    $expect(Database::table('jobs')->count() === 0);

    Queue::push(FeatureJob::class, ['queue' => 'mail'], 0, 3, 'mail');
    $expect(Queue::work(5, 'default') === 0);
    $expect(Queue::work(5, 'mail') === 1);
});

$test('queue recovers stale reservations and supports failed-job operations', static function () use ($expect): void {
    Queue::configure(['retry_after' => 30]);
    $id = Queue::push(FeatureJob::class, ['recovered' => true]);
    Database::table('jobs')->where('id', $id)->update(['reserved_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
    $expect(Queue::work(5) === 1);

    $failedId = Queue::push(FailingFeatureJob::class, [], 0, 1);
    $expect(Queue::work(5) === 1);
    $expect(count(Queue::failed()) === 1);
    $expect(Queue::retryFailed($failedId) === 1);
    $expect(Database::table('jobs')->where('id', $failedId)->whereNull('failed_at')->exists());
    Database::table('jobs')->where('id', $failedId)->update(['failed_at' => gmdate('Y-m-d H:i:s')]);
    $expect(Queue::flushFailed() === 1);
});

$test('scheduler runs due callbacks', static function () use ($expect): void {
    $runs = 0;
    $schedule = new Schedule();
    $schedule->call(static function () use (&$runs): void {
        $runs++;
    })->dailyAt('03:15');

    $count = $schedule->runDue(new DateTimeImmutable('2026-09-14 03:15:00'));
    $expect($count === 1 && $runs === 1);
    $expect($schedule->runDue(new DateTimeImmutable('2026-09-14 03:15:30')) === 0);
    $expect($runs === 1);
});

$test('scheduler supports cron expressions and timezones', static function () use ($expect): void {
    $runs = 0;
    $schedule = new Schedule();
    $schedule->call(static function () use (&$runs): void { $runs++; })
        ->cron('*/15 10-12 * * 1-5')
        ->timezone('Europe/Istanbul')
        ->description('cron timezone test');
    $expect($schedule->runDue(new DateTimeImmutable('2026-09-14 07:30:00 UTC')) === 1);
    $expect($runs === 1);
});

$test('scheduler does not count tasks skipped by overlap locks', static function () use ($expect): void {
    $schedule = new Schedule();
    $schedule->call(static function (): void {})->description('locked feature task')->withoutOverlapping();
    Cache::put('schedule:' . hash('sha256', 'task-0|locked feature task'), true, 60);
    $expect($schedule->runDue(new DateTimeImmutable('2026-09-14 04:00:00')) === 0);
});

$test('log mail driver accepts valid messages without external services', static function () use ($expect): void {
    $attachment = sys_get_temp_dir() . '/sedophp-attachment-' . getmypid() . '.txt';
    file_put_contents($attachment, 'attachment body');
    $expect(Mailer::send('receiver@example.com', 'Test mail', '<b>Hello</b>', 'Hello', [], [
        'cc' => ['copy@example.com'],
        'bcc' => ['hidden@example.com'],
        'attachments' => [['path' => $attachment, 'name' => 'report.txt']],
    ]));
    @unlink($attachment);
});

if (getenv('TEST_SMTP') === '1') {
    $test('SMTP transport sends a MIME message to a real socket server', static function () use ($expect): void {
        Mailer::configure([
            'driver' => 'smtp', 'host' => '127.0.0.1', 'port' => 2525, 'encryption' => '',
            'from_address' => 'tests@example.com', 'from_name' => 'SedoPHP Tests', 'timeout' => 5,
        ]);
        $expect(Mailer::send('receiver@example.com', 'SMTP integration', '<b>Hello</b>', 'Hello'));
        $capture = (string) getenv('TEST_SMTP_CAPTURE');
        $expect(is_file($capture));
        $expect(str_contains((string) file_get_contents($capture), 'Subject: SMTP integration'));
    });
}

foreach (glob($cacheDirectory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cacheDirectory);

echo "\nExtended: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
