<?php

declare(strict_types=1);

use SedoPHP\Auth\Auth;
use SedoPHP\Database\Database;
use SedoPHP\Database\Model;
use SedoPHP\Http\Request;
use SedoPHP\Http\Response;
use SedoPHP\Middleware\CsrfMiddleware;
use SedoPHP\Routing\Router;
use SedoPHP\Security\Csrf;
use SedoPHP\Session\Session;
use SedoPHP\Validation\Validator;

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
    $pdo->exec('DROP TABLE IF EXISTS users');
    $pdo->exec('CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $hasDatabase = true;
} elseif ($testDriver === 'sqlite' && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    Database::configure(['driver' => 'sqlite', 'sqlite' => ':memory:']);
    $pdo = Database::pdo();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL)');
    $hasDatabase = true;
}

$test('Response JSON encoding', static function () use ($expect): void {
    $response = Response::json(['ok' => true]);
    $expect($response->status() === 200);
    $expect($response->body() === '{"ok":true}');
});

$test('Request input merges query and body', static function () use ($expect): void {
    $request = Request::fake('POST', '/save?from=query', ['name' => 'Sedat']);
    $expect($request->input('from') === 'query');
    $expect($request->input('name') === 'Sedat');
});

$test('Router matches named parameters', static function () use ($expect): void {
    $router = new Router();
    $router->get('/hello/{name}', static fn (string $name) => new Response('Hello ' . $name));
    $response = $router->dispatch(Request::fake('GET', '/hello/Sedat'));
    $expect($response->status() === 200);
    $expect($response->body() === 'Hello Sedat');
});

$test('Router returns 404 for unknown route', static function () use ($expect): void {
    $router = new Router();
    $response = $router->dispatch(Request::fake('GET', '/missing'));
    $expect($response->status() === 404);
});

$test('Validation catches invalid input', static function () use ($expect): void {
    $errors = Validator::validate(['email' => 'bad', 'password' => '123'], [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ]);
    $expect(isset($errors['email']));
    $expect(isset($errors['password']));
});

if ($hasDatabase) {
    $test('Query builder inserts and reads safely', static function () use ($expect): void {
        $id = Database::table('users')->insert([
            'name' => 'Sedat',
            'email' => 'sedat@example.test',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
        ]);
        $row = Database::table('users')->where('id', $id)->first();
        $expect($row !== null && $row['name'] === 'Sedat');
        $expect(Database::table('users')->where('email', 'sedat@example.test')->count() === 1);
    });

    $test('Query builder update and delete', static function () use ($expect): void {
        $id = Database::table('users')->insert([
            'name' => 'Temp',
            'email' => 'temp@example.test',
            'password' => 'x',
        ]);
        Database::table('users')->where('id', $id)->update(['name' => 'Changed']);
        $expect(Database::table('users')->where('id', $id)->first()['name'] === 'Changed');
        $expect(Database::table('users')->where('id', $id)->delete() === 1);
    });

    final class TestUser extends Model
    {
        protected string $table = 'users';
        protected array $fillable = ['name', 'email', 'password'];
    }

    $test('Lightweight model create, find and update', static function () use ($expect): void {
        $user = TestUser::create([
            'name' => 'Model User',
            'email' => 'model@example.test',
            'password' => 'hidden',
        ]);
        $found = TestUser::find($user['id']);
        $expect($found !== null && $found['email'] === 'model@example.test');
        $found->update(['name' => 'Updated User']);
        $expect(TestUser::find($user['id'])['name'] === 'Updated User');
    });

    $test('Auth login and logout', static function () use ($expect): void {
        Database::table('users')->insert([
            'name' => 'Auth User',
            'email' => 'auth@example.test',
            'password' => password_hash('correct-horse', PASSWORD_DEFAULT),
        ]);
        Auth::configure([
            'table' => 'users',
            'id' => 'id',
            'identity' => 'email',
            'password' => 'password',
            'session_key' => '_test_auth',
            'login_path' => '/login',
        ]);
        $expect(Auth::attempt('auth@example.test', 'wrong') === false);
        $expect(Auth::attempt('auth@example.test', 'correct-horse') === true);
        $expect(Auth::check() === true);
        $expect(Auth::user()['name'] === 'Auth User');
        Auth::logout();
        $expect(Auth::check() === false);
    });

    $test('Transaction rolls back on exception', static function () use ($expect): void {
        $before = Database::table('users')->count();
        try {
            Database::transaction(static function (): void {
                Database::table('users')->insert(['name' => 'Rollback', 'email' => 'rollback@example.test', 'password' => 'x']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $expect(Database::table('users')->count() === $before);
    });

} else {
    echo "[SKIP] Database/model/auth/transaction tests: requested PDO test driver is unavailable.\n";
}

$test('CSRF middleware accepts valid token and rejects invalid token', static function () use ($expect): void {
    $router = new Router();
    $router->alias('csrf', CsrfMiddleware::class);
    $router->post('/form', static fn () => new Response('saved'))->middleware('csrf');

    $good = $router->dispatch(Request::fake('POST', '/form', ['_token' => Csrf::token()]));
    $bad = $router->dispatch(Request::fake('POST', '/form', ['_token' => 'bad-token']));
    $expect($good->status() === 200 && $good->body() === 'saved');
    $expect($bad->status() === 419);
});

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
