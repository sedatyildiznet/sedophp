<?php

declare(strict_types=1);

use SedoPHP\Auth\Auth;
use SedoPHP\Database\Database;
use SedoPHP\Database\MigrationRunner;
use SedoPHP\Database\Model;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Http\UploadedFile;
use SedoPHP\Middleware\CsrfMiddleware;
use SedoPHP\Routing\Router;
use SedoPHP\Security\Csrf;
use SedoPHP\Session\Session;
use SedoPHP\Validation\Validator;
use SedoPHP\View\View;

[$suite, $test, $expect] = require __DIR__ . '/Support/bootstrap.php';

ob_start();

Session::configure(['name' => 'sedophp_test_' . getmypid(), 'secure' => false, 'same_site' => 'Lax']);
Session::start();

$testDriver = (string) (getenv('TEST_DB_DRIVER') ?: 'sqlite');
$hasDatabase = false;

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
    $pdo = Database::pdo();
    $pdo->exec('DROP TABLE IF EXISTS sedo_migrations');
    $pdo->exec('DROP TABLE IF EXISTS users');
    $hasDatabase = true;
} elseif ($testDriver === 'sqlite' && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
    $hasDatabase = true;
}

$test('Response JSON encoding and HEAD body removal', static function () use ($expect): void {
    $response = Response::json(['ok' => true]);
    $expect($response->status() === 200);
    $expect($response->body() === '{"ok":true}');
    $expect($response->withoutBody()->body() === '');
});

$test('Request input, query merge and method override', static function () use ($expect): void {
    $request = Request::fake('POST', '/save?from=query', ['name' => 'Sedat', '_method' => 'PATCH']);
    $expect($request->input('from') === 'query');
    $expect($request->input('name') === 'Sedat');
    $expect($request->method() === 'PATCH');
});

$test('Router parameters, HEAD, OPTIONS and 405', static function () use ($expect): void {
    $router = new Router();
    $router->get('/hello/{name}', static fn (string $name) => new Response('Hello ' . $name));

    $get = $router->dispatch(Request::fake('GET', '/hello/Sedat'));
    $head = $router->dispatch(Request::fake('HEAD', '/hello/Sedat'));
    $options = $router->dispatch(Request::fake('OPTIONS', '/hello/Sedat'));
    $wrong = $router->dispatch(Request::fake('POST', '/hello/Sedat'));

    $expect($get->body() === 'Hello Sedat');
    $expect($head->status() === 200 && $head->body() === '');
    $expect($options->status() === 204 && str_contains($options->headers()['Allow'] ?? '', 'GET'));
    $expect($wrong->status() === 405 && str_contains($wrong->headers()['Allow'] ?? '', 'GET'));
});

$test('Router rejects duplicate routes', static function () use ($expect): void {
    $router = new Router();
    $router->get('/same', static fn () => 'one');

    $thrown = false;
    try {
        $router->get('/same', static fn () => 'two');
    } catch (RuntimeException) {
        $thrown = true;
    }

    $expect($thrown);
});

$test('Router returns 404 for unknown route', static function () use ($expect): void {
    $router = new Router();
    $expect($router->dispatch(Request::fake('GET', '/missing'))->status() === 404);
});

$test('Validation catches common invalid input', static function () use ($expect): void {
    $errors = Validator::validate([
        'email' => 'bad',
        'password' => '123',
        'age' => 'abc',
        'tags' => 'nope',
    ], [
        'email' => 'required|email',
        'password' => 'required|min:6',
        'age' => 'numeric',
        'tags' => 'array',
    ]);

    $expect(isset($errors['email'], $errors['password'], $errors['age'], $errors['tags']));
});

$test('Validation required rejects empty arrays, whitespace and invalid uploads', static function () use ($expect): void {
    $invalidUpload = new UploadedFile('', 'missing.txt', 'text/plain', 0, UPLOAD_ERR_NO_FILE);

    $errors = Validator::validate([
        'array' => [],
        'text' => '   ',
        'upload' => $invalidUpload,
    ], [
        'array' => 'required',
        'text' => 'required',
        'upload' => 'required|file',
    ]);

    $expect(isset($errors['array'], $errors['text'], $errors['upload']));
});

$test('back() accepts same origin with port and rejects external origins', static function () use ($expect): void {
    $oldHost = $_SERVER['HTTP_HOST'] ?? null;
    $oldReferer = $_SERVER['HTTP_REFERER'] ?? null;
    $oldHttps = $_SERVER['HTTPS'] ?? null;

    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['HTTP_REFERER'] = 'http://localhost:8000/form';
    unset($_SERVER['HTTPS']);

    $same = back();
    $expect(($same->headers()['Location'] ?? '') === 'http://localhost:8000/form');

    $_SERVER['HTTP_REFERER'] = 'http://evil.example/form';
    $external = back();
    $expect(($external->headers()['Location'] ?? '') === '/');

    if ($oldHost === null) {
        unset($_SERVER['HTTP_HOST']);
    } else {
        $_SERVER['HTTP_HOST'] = $oldHost;
    }

    if ($oldReferer === null) {
        unset($_SERVER['HTTP_REFERER']);
    } else {
        $_SERVER['HTTP_REFERER'] = $oldReferer;
    }

    if ($oldHttps === null) {
        unset($_SERVER['HTTPS']);
    } else {
        $_SERVER['HTTPS'] = $oldHttps;
    }
});

$test('Relative SQLite paths resolve from the project root', static function () use ($expect): void {
    putenv('DB_SQLITE=storage/custom.sqlite');
    $config = require dirname(__DIR__) . '/config/database.php';
    putenv('DB_SQLITE');

    $expected = str_replace('\\\\', '/', dirname(__DIR__) . '/storage/custom.sqlite');
    $actual = str_replace('\\\\', '/', (string) $config['sqlite']);
    $expect($actual === $expected, "SQLite path was {$actual}");
});

$test('Session set and pull', static function () use ($expect): void {
    Session::set('temporary', 'value');
    $expect(Session::pull('temporary') === 'value');
    $expect(Session::get('temporary') === null);
});

$test('CSRF middleware accepts valid token and rejects invalid token', static function () use ($expect): void {
    $router = new Router();
    $router->alias('csrf', CsrfMiddleware::class);
    $router->post('/form', static fn () => new Response('saved'))->middleware('csrf');

    $good = $router->dispatch(Request::fake('POST', '/form', ['_token' => Csrf::token()]));
    $bad = $router->dispatch(Request::fake('POST', '/form', ['_token' => 'bad-token']));

    $expect($good->status() === 200 && $good->body() === 'saved');
    $expect($bad->status() === 419);
});

$test('Plain PHP views render safely without renderer variable collisions', static function () use ($expect): void {
    View::configure(dirname(__DIR__) . '/app/Views');
    $response = View::render('home', ['name' => 'Test App', 'version' => 'test']);
    $expect(str_contains($response->body(), 'Test App'));
});

$test('Shared-hosting protection files contain required rules', static function () use ($expect): void {
    $rootRules = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');
    $publicRules = (string) file_get_contents(dirname(__DIR__) . '/public/.htaccess');

    $expect(str_contains($rootRules, 'storage'));
    $expect(str_contains($rootRules, 'public/$1'));
    $expect(str_contains($publicRules, 'index.php'));
});

$test('UploadedFile blocks executable extensions and saves normal uploads', static function () use ($expect): void {
    $source = tempnam(sys_get_temp_dir(), 'sedo_upload_');
    file_put_contents($source, 'hello');
    $file = UploadedFile::fake($source, 'note.txt');
    $targetDir = sys_get_temp_dir() . '/sedophp_upload_' . bin2hex(random_bytes(4));
    $saved = $file->save($targetDir);
    $expect(is_file($saved));
    @unlink($saved);
    @rmdir($targetDir);

    $source = tempnam(sys_get_temp_dir(), 'sedo_upload_');
    file_put_contents($source, '<?php echo 1;');
    $blocked = UploadedFile::fake($source, 'shell.php');
    $thrown = false;
    try {
        $blocked->save(sys_get_temp_dir());
    } catch (RuntimeException) {
        $thrown = true;
    }
    @unlink($source);
    $expect($thrown);
});

if ($hasDatabase) {
    $runner = new MigrationRunner(dirname(__DIR__) . '/database/migrations');

    $test('Migrations run, rollback and rerun on the configured database', static function () use ($expect, $runner): void {
        $migrationCount = count(glob(dirname(__DIR__) . '/database/migrations/*.php') ?: []);

        $expect($migrationCount >= 1);
        $expect($runner->migrate() === $migrationCount);
        $expect(Database::table('users')->count() === 0);
        $expect($runner->rollback() === $migrationCount);

        $missing = false;
        try {
            Database::table('users')->count();
        } catch (Throwable) {
            $missing = true;
        }
        $expect($missing);

        $expect($runner->migrate() === $migrationCount);
    });

    $test('Query builder insert, NULL, IN, value, pluck and exists', static function () use ($expect): void {
        $first = Database::table('users')->insert([
            'name' => 'Sedat',
            'email' => 'sedat@example.test',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
        ]);
        $second = Database::table('users')->insert([
            'name' => 'Second',
            'email' => 'second@example.test',
            'password' => 'x',
        ]);

        $expect(Database::table('users')->where('id', $first)->exists());
        $expect(Database::table('users')->whereIn('id', [$first, $second])->count() === 2);
        $expect(Database::table('users')->whereNotIn('id', [$first])->count() >= 1);
        $expect(Database::table('users')->where('id', null)->count() === 0);
        $expect(Database::table('users')->where('id', $first)->value('name') === 'Sedat');
        $expect(in_array('Sedat', Database::table('users')->orderBy('id')->pluck('name'), true));
    });

    $test('Query builder destructive operations require where', static function () use ($expect): void {
        $updateBlocked = false;
        $deleteBlocked = false;

        try {
            Database::table('users')->update(['name' => 'unsafe']);
        } catch (InvalidArgumentException) {
            $updateBlocked = true;
        }

        try {
            Database::table('users')->delete();
        } catch (InvalidArgumentException) {
            $deleteBlocked = true;
        }

        $expect($updateBlocked && $deleteBlocked);
    });

    final class TestUser extends Model
    {
        protected string $table = 'users';
        protected array $fillable = ['name', 'email', 'password'];
    }

    final class LockedUser extends Model
    {
        protected string $table = 'users';
    }

    $test('Models require explicit fillable fields', static function () use ($expect): void {
        $thrown = false;
        try {
            LockedUser::create(['name' => 'Unsafe']);
        } catch (RuntimeException) {
            $thrown = true;
        }
        $expect($thrown);
    });

    $test('Lightweight model create, find and update', static function () use ($expect): void {
        $user = TestUser::create([
            'name' => 'Model User',
            'email' => 'model@example.test',
            'password' => 'hidden',
            'admin' => true,
        ]);
        $found = TestUser::find($user['id']);
        $expect($found !== null && $found['email'] === 'model@example.test');
        $expect(!array_key_exists('password', $found->toArray()));
        $found->update(['name' => 'Updated User']);
        $expect(TestUser::find($user['id'])['name'] === 'Updated User');
    });

    $test('Validation unique and exists rules use prepared query builder', static function () use ($expect): void {
        $errors = Validator::validate([
            'email' => 'sedat@example.test',
            'user_id' => 999999,
        ], [
            'email' => 'unique:users,email',
            'user_id' => 'exists:users,id',
        ]);

        $expect(isset($errors['email'], $errors['user_id']));
    });

    $test('Auth hides password hashes and invalidates missing users', static function () use ($expect): void {
        $id = Database::table('users')->insert([
            'name' => 'Auth User',
            'email' => 'auth@example.test',
            'password' => password_hash('correct-horse', PASSWORD_DEFAULT),
        ]);

        $config = [
            'table' => 'users',
            'id' => 'id',
            'identity' => 'email',
            'password' => 'password',
            'session_key' => '_test_auth',
            'login_path' => '/login',
        ];

        Auth::configure($config);
        $expect(Auth::attempt('auth@example.test', 'wrong') === false);
        $expect(Auth::attempt('auth@example.test', 'correct-horse') === true);
        $expect(Auth::check() === true);
        $expect(Auth::user()['name'] === 'Auth User');
        $expect(!array_key_exists('password', Auth::user()));

        Database::table('users')->where('id', $id)->delete();
        Auth::configure($config);
        $expect(Auth::check() === false);
        Auth::logout();
    });

    $test('Transaction rolls back on exception', static function () use ($expect): void {
        $before = Database::table('users')->count();

        try {
            Database::transaction(static function (): void {
                Database::table('users')->insert([
                    'name' => 'Rollback',
                    'email' => 'rollback@example.test',
                    'password' => 'x',
                ]);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $expect(Database::table('users')->count() === $before);
    });
} else {
    echo "[SKIP] Database tests: requested PDO test driver is unavailable.\n";
}

$code = $suite->finish();
ob_end_flush();
exit($code);
