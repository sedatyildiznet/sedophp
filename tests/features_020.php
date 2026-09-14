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
use SedoPHP\Mail\Mailer;
use SedoPHP\Queue\JobInterface;
use SedoPHP\Queue\Queue;
use SedoPHP\Scheduling\Schedule;
use SedoPHP\Security\Jwt;
use SedoPHP\Security\RateLimiter;

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

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[SKIP] Extended features require PDO SQLite.\n";
    exit(0);
}

Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
(new MigrationRunner(dirname(__DIR__) . '/database/migrations'))->migrate();
$pdo = Database::pdo();
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');

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
});

$test('JWT signs, validates and rejects tampering or invalid registered claims', static function () use ($expect): void {
    $token = Jwt::encode(['sub' => 7], 300);
    $claims = Jwt::decode($token);

    $expect(($claims['sub'] ?? null) === 7);
    $expect(Jwt::decode($token . 'x') === null);

    Jwt::configure([
        'jwt_secret' => str_repeat('s', 48),
        'jwt_issuer' => '',
    ]);
    $missingIssuer = Jwt::encode(['sub' => 7], 300);
    $invalidExpiration = Jwt::encode(['sub' => 7, 'exp' => 'not-a-timestamp'], 300);

    Jwt::configure([
        'jwt_secret' => str_repeat('s', 48),
        'jwt_issuer' => 'sedophp-tests',
    ]);

    $expect(Jwt::decode($missingIssuer) === null);
    $expect(Jwt::decode($invalidExpiration) === null);
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

$test('database queue dispatches, recovers stale reservations and executes jobs', static function () use ($expect): void {
    FeatureJob::$handled = [];

    $staleId = Queue::push(FeatureJob::class, ['id' => 98]);
    Database::table('jobs')->where('id', $staleId)->update([
        'reserved_at' => gmdate('Y-m-d H:i:s', time() - 7200),
    ]);

    $expect(Queue::releaseStale(3600) === 1);
    $expect(Database::table('jobs')->where('id', $staleId)->value('reserved_at') === null);

    Queue::push(FeatureJob::class, ['id' => 99]);
    $processed = Queue::work(5);

    $expect($processed === 2);
    $handledIds = array_column(FeatureJob::$handled, 'id');
    sort($handledIds);
    $expect($handledIds === [98, 99]);
    $expect(Database::table('jobs')->count() === 0);
});

$test('scheduler runs due callbacks', static function () use ($expect): void {
    $runs = 0;
    $schedule = new Schedule();
    $schedule->call(static function () use (&$runs): void {
        $runs++;
    })->dailyAt('03:15');

    $count = $schedule->runDue(new DateTimeImmutable('2026-09-14 03:15:00'));
    $expect($count === 1 && $runs === 1);
});

$test('log mail driver accepts valid messages without external services', static function () use ($expect): void {
    $expect(Mailer::send('receiver@example.com', 'Test mail', '<b>Hello</b>', 'Hello'));
});

foreach (glob($cacheDirectory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cacheDirectory);

echo "\nExtended: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
