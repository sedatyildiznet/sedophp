<?php

declare(strict_types=1);

use SedoPHP\Database\Database;
use SedoPHP\Database\Model;
use SedoPHP\Database\QueryBuilder;
use SedoPHP\Database\Schema;
use SedoPHP\Database\Blueprint;
use SedoPHP\Database\Relations\BelongsTo;
use SedoPHP\Database\Relations\BelongsToMany;
use SedoPHP\Database\Relations\HasMany;
use SedoPHP\Database\Relations\HasOne;

[$suite, $test, $expect] = require __DIR__ . '/Support/bootstrap.php';

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

foreach (['m1_role_user', 'm1_posts', 'm1_profiles', 'm1_roles', 'm1_settings', 'm1_users'] as $table) {
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

exit($suite->finish('0.3 M1'));
