<?php

declare(strict_types=1);

use SedoPHP\Auth\Auth;
use SedoPHP\Auth\EmailVerification;
use SedoPHP\Auth\PasswordReset;
use SedoPHP\Cache\Cache;
use SedoPHP\Console\ConsoleKernel;
use SedoPHP\Core\Config;
use SedoPHP\Core\Optimizer;
use SedoPHP\Database\Database;
use SedoPHP\Database\Model;
use SedoPHP\Database\ModelFactory;
use SedoPHP\Database\QueryBuilder;
use SedoPHP\Database\Schema;
use SedoPHP\Database\Blueprint;
use SedoPHP\Database\SeederRunner;
use SedoPHP\Http\FormRequest;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\SignedUrlMiddleware;
use SedoPHP\Routing\Router;
use SedoPHP\Security\SignedUrl;
use SedoPHP\Session\Session;
use SedoPHP\Testing\DatabaseAssertions;
use SedoPHP\Testing\TestClient;
use SedoPHP\Database\Relations\BelongsTo;
use SedoPHP\Database\Relations\BelongsToMany;
use SedoPHP\Database\Relations\HasMany;
use SedoPHP\Database\Relations\HasOne;

[$suite, $test, $expect] = require __DIR__ . '/Support/bootstrap.php';

$m3CacheDirectory = sys_get_temp_dir() . '/sedophp_030_cache_' . getmypid();
Cache::configure(['path' => $m3CacheDirectory, 'prefix' => 'm3_'], dirname(__DIR__));
Config::set('app.key', str_repeat('k', 48));
Session::configure([
    'name' => 'sedophp_030_' . getmypid(),
    'secure' => false,
    'same_site' => 'Lax',
]);
Session::start();

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
} elseif ($testDriver === 'sqlite' && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
} else {
    echo "[SKIP] 0.3 features: requested PDO test driver is unavailable.\n";
    exit(0);
}

$pdo = Database::pdo();

foreach (['m1_role_user', 'm1_posts', 'm1_profiles', 'm1_roles', 'm1_settings', 'm1_users', 'm3_auth_users'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS ' . $table);
}

if ($testDriver === 'mysql') {
    $pdo->exec('CREATE TABLE m1_users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        deleted_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m1_profiles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        bio VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m1_posts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m1_roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m1_role_user (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        level VARCHAR(32) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m1_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(191) NOT NULL UNIQUE,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE m3_auth_users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} else {
    $pdo->exec('CREATE TABLE m1_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        deleted_at TEXT NULL
    )');
    $pdo->exec('CREATE TABLE m1_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        bio TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE m1_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE m1_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE m1_role_user (
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        level TEXT NULL
    )');
    $pdo->exec('CREATE TABLE m1_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE m3_auth_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )');
}

final class M1User extends Model
{
    protected string $table = 'm1_users';
    protected array $fillable = ['name'];
    protected bool $softDeletes = true;

    public function profile(): HasOne
    {
        return $this->hasOne(M1Profile::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(M1Post::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            M1Role::class,
            'm1_role_user',
            'user_id',
            'role_id'
        )->withPivot('level');
    }
}

final class M1Profile extends Model
{
    protected string $table = 'm1_profiles';
    protected array $fillable = ['user_id', 'bio'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(M1User::class, 'user_id');
    }
}

final class M1Post extends Model
{
    protected string $table = 'm1_posts';
    protected array $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(M1User::class, 'user_id');
    }
}

final class M1Role extends Model
{
    protected string $table = 'm1_roles';
    protected array $fillable = ['name'];
}

final class M3StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
        ];
    }
}

final class M1UserFactory extends ModelFactory
{
    protected string $model = M1User::class;

    protected function definition(): array
    {
        return ['name' => 'Factory ' . $this->randomString(12)];
    }
}

$user = M1User::create(['name' => 'Sedat']);
$profile = M1Profile::create(['user_id' => $user->getKey(), 'bio' => 'Developer']);
M1Post::create(['user_id' => $user->getKey(), 'title' => 'Visible']);
M1Post::create(['user_id' => $user->getKey(), 'title' => 'Hidden by constraint']);
$admin = M1Role::create(['name' => 'admin']);
$editor = M1Role::create(['name' => 'editor']);

Database::table('m1_role_user')->insert([
    'user_id' => $user->getKey(),
    'role_id' => $admin->getKey(),
    'level' => 'owner',
]);
Database::table('m1_role_user')->insert([
    'user_id' => $user->getKey(),
    'role_id' => $editor->getKey(),
    'level' => 'member',
]);

$test('named routes generate encoded paths and query strings', static function () use ($expect): void {
    $router = new Router();
    $router->get('/users/{id}', static fn (string $id): string => $id)->name('users.show');

    $path = $router->pathFor('users.show', [
        'id' => 'user 7',
        'tab' => 'posts',
    ]);

    $expect($path === '/users/user%207?tab=posts');
});

$test('signed URLs validate path and expiry through middleware', static function () use ($expect): void {
    $valid = SignedUrl::sign('/verify/7?source=test', '+5 minutes');
    $expect(SignedUrl::validate(Request::fake('GET', $valid)));
    $expect(!SignedUrl::validate(Request::fake('GET', str_replace('/verify/7', '/verify/8', $valid))));

    $expired = SignedUrl::sign('/verify/7', new DateTimeImmutable('-1 minute'));
    $expect(!SignedUrl::validate(Request::fake('GET', $expired)));

    $router = new Router();
    $router->alias('signed', SignedUrlMiddleware::class);
    $router->get('/verify/{id}', static fn (string $id): Response => Response::json(['id' => $id]))
        ->middleware('signed');

    $allowed = $router->dispatch(Request::fake('GET', SignedUrl::sign('/verify/7', '+5 minutes')));
    $denied = $router->dispatch(Request::fake('GET', '/verify/7?signature=' . str_repeat('0', 64), [], [
        'Accept' => 'application/json',
    ]));

    $expect($allowed->status() === 200);
    $expect($denied->status() === 403);
});

$test('FormRequest keeps validation optional and returns validated input', static function () use ($expect): void {
    $valid = new M3StoreUserRequest(Request::fake('POST', '/users', [
        'name' => 'Sedat',
        'email' => 'sedat@example.test',
        'ignored' => 'value',
    ]));

    $expect($valid->passes());
    $expect($valid->validated() === [
        'name' => 'Sedat',
        'email' => 'sedat@example.test',
    ]);

    $invalid = new M3StoreUserRequest(Request::fake('POST', '/users', [
        'name' => '',
        'email' => 'invalid',
    ]));

    $expect($invalid->fails());
    $expect(isset($invalid->errors()['name'], $invalid->errors()['email']));
});

$test('auth throttles repeated login attempts and can clear the limit', static function () use ($expect): void {
    $id = Database::table('m3_auth_users')->insert([
        'email' => 'auth030@example.test',
        'password' => password_hash('correct-password', PASSWORD_DEFAULT),
    ]);

    Auth::configure([
        'table' => 'm3_auth_users',
        'id' => 'id',
        'identity' => 'email',
        'password' => 'password',
        'session_key' => '_sedo_030_auth',
        'login_max_attempts' => 2,
        'login_decay_seconds' => 60,
    ]);

    $expect(!Auth::attempt('auth030@example.test', 'wrong-one'));
    $expect(!Auth::attempt('auth030@example.test', 'wrong-two'));
    $expect(!Auth::attempt('auth030@example.test', 'correct-password'));

    Auth::clearLoginAttempts('auth030@example.test');
    $expect(Auth::attempt('auth030@example.test', 'correct-password'));
    $expect(Auth::id() == $id);
    Auth::logout();
});

$test('password reset tokens are hashed, expiring and single use', static function () use ($expect): void {
    $id = Database::table('m3_auth_users')->where('email', 'auth030@example.test')->value('id');

    Auth::configure([
        'table' => 'm3_auth_users',
        'id' => 'id',
        'identity' => 'email',
        'password' => 'password',
        'session_key' => '_sedo_030_auth',
        'login_max_attempts' => 0,
    ]);

    $token = PasswordReset::issue((int) $id, 60);
    $expect(PasswordReset::reset($token, 'new-password'));
    $expect(!PasswordReset::reset($token, 'another-password'));

    $hash = Database::table('m3_auth_users')->where('id', $id)->value('password');
    $expect(is_string($hash) && password_verify('new-password', $hash));

    $revoked = PasswordReset::issue((int) $id, 60);
    PasswordReset::revoke($revoked);
    $expect(!PasswordReset::reset($revoked, 'revoked-password'));
});

$test('email verification tokens preserve metadata and are single use', static function () use ($expect): void {
    $token = EmailVerification::issue(77, ['email' => 'verify@example.test'], 60);
    $payload = EmailVerification::verify($token);

    $expect(($payload['subject'] ?? null) === 77);
    $expect(($payload['metadata']['email'] ?? null) === 'verify@example.test');
    $expect(EmailVerification::verify($token) === null);
});

$test('model factories create native-PHP test data without Faker', static function () use ($expect): void {
    $made = M1UserFactory::new()->state(['name' => 'Unsaved factory'])->make();
    $expect($made instanceof M1User);
    $expect($made->getKey() === null);

    $created = M1UserFactory::new()->count(2)->create();
    $expect(is_array($created));
    $expect(count($created) === 2);
    $expect($created[0] instanceof M1User);
    $expect($created[0]->getKey() !== null);
});

$test('seeder runner loads plain PHP seeders from a directory', static function () use ($expect): void {
    $directory = sys_get_temp_dir() . '/sedophp_seeders_' . getmypid();
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $file = $directory . '/FeatureSeeder.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use SedoPHP\Database\Database;
use SedoPHP\Database\Seeder;

final class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        Database::table('m1_settings')->updateOrInsert(
            ['setting_key' => 'seeded'],
            ['setting_value' => 'yes']
        );
    }
}
PHP;

    file_put_contents($file, $source);
    $expect((new SeederRunner($directory))->run('FeatureSeeder') === 1);
    $expect(Database::table('m1_settings')->where('setting_key', 'seeded')->value('setting_value') === 'yes');

    @unlink($file);
    @rmdir($directory);
});

$test('test client provides HTTP and JSON assertions without PHPUnit', static function () use ($expect): void {
    $router = new Router();
    $router->get('/testing/json', static fn (): Response => Response::json([
        'ok' => true,
        'user' => ['id' => 7, 'name' => 'Sedat'],
    ]));
    $router->get('/testing/redirect', static fn (): Response => Response::redirect('/target'));

    $client = new TestClient($router);

    $client->get('/testing/json')
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertJson(['ok' => true, 'user' => ['id' => 7]])
        ->assertJsonPath('user.name', 'Sedat')
        ->assertSee('"ok":true');

    $client->get('/testing/redirect')->assertRedirect('/target');

    $expect(true);
});

$test('database assertions check expected and missing rows', static function () use ($expect): void {
    DatabaseAssertions::assertHas('m1_settings', [
        'setting_key' => 'seeded',
        'setting_value' => 'yes',
    ]);
    DatabaseAssertions::assertMissing('m1_settings', [
        'setting_key' => 'definitely_missing',
    ]);

    $expect(true);
});

$test('query builder supports upsert and convenience writes', static function () use ($expect): void {
    $table = Database::table('m1_settings');

    $expect($table->upsert([
        ['setting_key' => 'mode', 'setting_value' => 'one'],
    ], 'setting_key', ['setting_value']) >= 1);

    $table->upsert([
        ['setting_key' => 'mode', 'setting_value' => 'two'],
        ['setting_key' => 'theme', 'setting_value' => 'dark'],
    ], 'setting_key', ['setting_value']);

    $expect(Database::table('m1_settings')->where('setting_key', 'mode')->value('setting_value') === 'two');
    $expect(Database::table('m1_settings')->where('setting_key', 'theme')->value('setting_value') === 'dark');

    $expect(Database::table('m1_settings')->updateOrInsert(
        ['setting_key' => 'theme'],
        ['setting_value' => 'light']
    ));
    $expect(Database::table('m1_settings')->where('setting_key', 'theme')->value('setting_value') === 'light');

    $created = Database::table('m1_settings')->firstOrCreate(
        ['setting_key' => 'locale'],
        ['setting_value' => 'tr']
    );
    $expect(($created['setting_value'] ?? null) === 'tr');

    $new = Database::table('m1_settings')->firstOrNew(
        ['setting_key' => 'timezone'],
        ['setting_value' => 'Europe/Istanbul']
    );
    $expect(($new['setting_value'] ?? null) === 'Europe/Istanbul');
    $expect(!Database::table('m1_settings')->where('setting_key', 'timezone')->exists());
});

$test('query builder supports chunk, cursor and EXISTS subqueries', static function () use ($expect): void {
    $chunks = 0;
    $processed = Database::table('m1_roles')->orderBy('id')->chunk(1, static function (array $rows) use (&$chunks): void {
        $chunks++;
    });

    $expect($processed === 2);
    $expect($chunks === 2);

    $cursorRows = iterator_to_array(Database::table('m1_roles')->orderBy('id')->cursor(1), false);
    $expect(count($cursorRows) === 2);

    $subquery = Database::table('m1_posts')
        ->select('id')
        ->whereColumn('m1_posts.user_id', '=', 'm1_users.id')
        ->where('title', 'Visible');

    $expect(Database::table('m1_users')->whereExists($subquery)->count() === 1);
    $expect(Database::table('m1_users')->whereNotExists($subquery)->count() === 0);
});

$test('nested transactions use savepoints without rolling back the outer transaction', static function () use ($expect): void {
    Database::transaction(static function (): void {
        Database::table('m1_settings')->insert([
            'setting_key' => 'outer_before',
            'setting_value' => 'kept',
        ]);

        try {
            Database::transaction(static function (): void {
                Database::table('m1_settings')->insert([
                    'setting_key' => 'inner_rollback',
                    'setting_value' => 'removed',
                ]);
                throw new RuntimeException('rollback nested transaction');
            });
        } catch (RuntimeException) {
        }

        Database::table('m1_settings')->insert([
            'setting_key' => 'outer_after',
            'setting_value' => 'kept',
        ]);
    });

    $expect(Database::table('m1_settings')->where('setting_key', 'outer_before')->exists());
    $expect(Database::table('m1_settings')->where('setting_key', 'outer_after')->exists());
    $expect(!Database::table('m1_settings')->where('setting_key', 'inner_rollback')->exists());
});

$test('schema builder supports soft deletes, composite indexes, foreign keys and renameColumn', static function () use ($expect, $testDriver): void {
    Schema::dropIfExists('m1_schema_posts');

    Schema::create('m1_schema_posts', static function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->string('title');
        $table->softDeletes();
        $table->unique(['user_id', 'title']);
        $table->foreign('user_id', 'm1_users', 'id', onDelete: 'CASCADE');
    });

    $expect(Schema::hasColumn('m1_schema_posts', 'deleted_at'));
    $expect(Schema::hasColumn('m1_schema_posts', 'title'));

    Schema::table('m1_schema_posts', static function (Blueprint $table): void {
        $table->renameColumn('title', 'headline');
    });

    $expect(Schema::hasColumn('m1_schema_posts', 'headline'));
    $expect(!Schema::hasColumn('m1_schema_posts', 'title'));

    $userId = Database::table('m1_users')->value('id');
    Database::table('m1_schema_posts')->insert([
        'user_id' => $userId,
        'headline' => 'Schema test',
        'deleted_at' => null,
    ]);

    if ($testDriver === 'sqlite') {
        $blocked = false;
        try {
            Database::table('m1_schema_posts')->insert([
                'user_id' => 999999,
                'headline' => 'Invalid owner',
                'deleted_at' => null,
            ]);
        } catch (PDOException) {
            $blocked = true;
        }
        $expect($blocked, 'SQLite foreign-key enforcement is disabled.');
    }

    Schema::drop('m1_schema_posts');
});

$test('hasOne returns one related model', static function () use ($expect, $user, $profile): void {
    $related = $user->profile()->first();

    $expect($related instanceof M1Profile);
    $expect($related?->getKey() === $profile->getKey());
});

$test('belongsToMany hydrates related models and requested pivot data', static function () use ($expect, $user): void {
    $roles = $user->roles()->get();

    $expect(count($roles) === 2);
    $expect($roles[0] instanceof M1Role);
    $expect(in_array($roles[0]->get('pivot.level'), ['owner', 'member'], true) === false);
    $pivot = $roles[0]->get('pivot', []);
    $expect(isset($pivot['user_id'], $pivot['role_id'], $pivot['level']));
});

$test('belongsToMany supports attach, detach and sync without extra dependencies', static function () use ($expect, $user, $admin, $editor): void {
    $expect($user->roles()->detach($editor->getKey()) === 1);
    $expect($user->roles()->count() === 1);

    $expect($user->roles()->attach($editor->getKey(), ['level' => 'restored']));
    $expect($user->roles()->count() === 2);

    $result = $user->roles()->sync([$admin->getKey()]);
    $expect(count($result['detached']) === 1);
    $expect($user->roles()->count() === 1);

    $user->roles()->attach($editor->getKey(), ['level' => 'member']);
    $expect($user->roles()->count() === 2);
});

$test('eager loading supports hasOne, belongsToMany and constraints', static function () use ($expect): void {
    $users = M1User::with([
        'profile',
        'roles',
        'posts' => static fn (QueryBuilder $query): QueryBuilder => $query->where('title', 'Visible'),
    ]);

    $expect(count($users) === 1);
    $loaded = $users[0];
    $expect($loaded->get('profile') instanceof M1Profile);
    $expect(count($loaded->get('roles', [])) === 2);
    $expect(count($loaded->get('posts', [])) === 1);
    $expect($loaded->get('posts')[0]->get('title') === 'Visible');
});

$test('nested eager loading works across related models', static function () use ($expect): void {
    $users = M1User::with('posts.user');

    $expect(count($users) === 1);
    $posts = $users[0]->get('posts', []);
    $expect(count($posts) === 2);
    $expect($posts[0]->get('user') instanceof M1User);
});

$test('soft deletes hide rows by default and support restore', static function () use ($expect, $user): void {
    $id = $user->getKey();

    $expect($user->delete());
    $expect($user->trashed());
    $expect(M1User::find($id) === null);
    $expect(M1User::query()->where('id', $id)->first() === null);
    $expect(M1User::withTrashed()->where('id', $id)->first() !== null);
    $expect(M1User::onlyTrashed()->where('id', $id)->first() !== null);

    $expect($user->restore());
    $expect(!$user->trashed());
    $expect(M1User::find($id) instanceof M1User);
});

$test('forceDelete permanently removes a soft-deletable model', static function () use ($expect): void {
    $temporary = M1User::create(['name' => 'Temporary']);
    $id = $temporary->getKey();

    $expect($temporary->delete());
    $expect($temporary->forceDelete());
    $expect(M1User::withTrashed()->where('id', $id)->first() === null);
});

$test('custom console kernel discovers and runs plain PHP commands', static function () use ($expect): void {
    $directory = sys_get_temp_dir() . '/sedophp_commands_' . getmypid();
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $file = $directory . '/Feature030Command.php';
    $source = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use SedoPHP\Console\Command;

final class Feature030Command extends Command
{
    protected string $name = 'app:feature030';
    protected string $description = 'Feature test command';

    public function handle(array $arguments): int
    {
        return count($arguments);
    }
}
PHP;

    file_put_contents($file, $source);

    $kernel = new ConsoleKernel();
    $kernel->discover($directory);

    $expect($kernel->has('app:feature030'));
    $expect(($kernel->commands()['app:feature030'] ?? null) === 'Feature test command');
    $expect($kernel->run('app:feature030', ['one', 'two']) === 2);

    @unlink($file);
    @rmdir($directory);
});

$test('optimizer writes config and route metadata caches and clears cleanly', static function () use ($expect): void {
    $base = sys_get_temp_dir() . '/sedophp_optimize_' . getmypid();
    $configDirectory = $base . '/config';
    mkdir($configDirectory, 0775, true);

    file_put_contents($configDirectory . '/app.php', <<<'PHP'
<?php
return ['source' => 'file'];
PHP);

    $router = new Router();
    $router->get('/optimized/{id}', static fn (string $id): string => $id)
        ->name('optimized.show');

    $files = Optimizer::build(
        $base,
        ['app' => ['source' => 'cache']],
        $router->routes()
    );

    $expect(count($files) === 2);
    $expect(is_file(Optimizer::configFile($base)));
    $expect(is_file(Optimizer::routesFile($base)));

    Config::load($configDirectory, Optimizer::configFile($base));
    $expect(Config::get('app.source') === 'cache');

    $manifest = require Optimizer::routesFile($base);
    $expect(($manifest[0]['name'] ?? null) === 'optimized.show');
    $expect(($manifest[0]['path'] ?? null) === '/optimized/{id}');

    $expect(Optimizer::clear($base) === 2);

    Config::load($configDirectory, Optimizer::configFile($base));
    $expect(Config::get('app.source') === 'file');

    @unlink($configDirectory . '/app.php');
    @rmdir($configDirectory);
    @rmdir($base . '/bootstrap/cache');
    @rmdir($base . '/bootstrap');
    @rmdir($base);
});

foreach (glob($m3CacheDirectory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($m3CacheDirectory);

exit($suite->finish('0.3'));
