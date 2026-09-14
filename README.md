# SedoPHP

**PHP gibi yaz. Framework gibi çalışsın.**

SedoPHP is a small PHP 8.3+ framework designed around readable PHP, shared hosting, predictable behavior and almost no setup. It deliberately avoids a large dependency tree, template-language lock-in and hidden application magic.

> Status: **0.1.0-dev** — usable development preview. The public API may still change before 0.1.0 stable.

## What makes it different?

- PHP 8.3+
- Works on ordinary cPanel/shared hosting
- Zero third-party runtime dependencies
- Composer-compatible, but Composer is not required on the server
- Plain PHP views
- Small functional API: `get()`, `input()`, `db()`, `view()`, `auth()`...
- PDO prepared statements
- Lightweight models instead of a large ORM
- Sessions, CSRF, validation and simple authentication included
- JSON requests/responses for APIs
- CLI is useful, never mandatory
- PSR-4 project layout and PSR-12-oriented source style

## Quick start

```bash
git clone https://github.com/sedatyildiznet/sedophp.git mysite
cd mysite
cp .env.example .env
php sedo serve
```

Open `http://127.0.0.1:8000`.

Composer is optional for local development:

```bash
composer install
```

If `vendor/autoload.php` does not exist, SedoPHP uses its own tiny PSR-4-compatible fallback autoloader.

## Routes

```php
get('/', function () {
    return view('home');
});

get('/users/{id}', 'UserController@show');

post('/login', 'AuthController@login')->middleware('csrf');
```

A controller stays ordinary PHP:

```php
final class UserController
{
    public function show(string $id)
    {
        $user = db('users')->where('id', $id)->first();

        if (!$user) {
            return response('User not found', 404);
        }

        return view('users.show', ['user' => $user]);
    }
}
```

## Input

```php
$email = input('email');
$data = input_all();
```

JSON request bodies are read automatically when `Content-Type: application/json` is sent.

## Database

```php
$users = db('users')
    ->where('active', 1)
    ->orderBy('name')
    ->get();

$user = db('users')->where('id', 5)->first();

$id = db('users')->insert([
    'name' => 'Sedat',
    'email' => 'sedat@example.com',
]);
```

Updates and deletes deliberately require a `where()` clause.

```php
db('users')->where('id', 5)->update(['name' => 'Sedat Yıldız']);
db('users')->where('id', 5)->delete();
```

## Models

```php
final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
}

$user = User::find(1);
echo $user['name'];
```

The model layer is intentionally small. For most application code, `db()` is the primary database API.

## Validation

```php
$errors = validate(input_all(), [
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

if ($errors) {
    return view('login', ['errors' => $errors], 422);
}
```

## Session and CSRF

```php
session_set('theme', 'dark');
$theme = session('theme');
flash('success', 'Saved.');
```

Forms that modify data should use the `csrf` middleware and include the token:

```php
post('/profile', 'ProfileController@save')->middleware('csrf');
```

```php
<form method="post" action="/profile">
    <?= csrf() ?>
</form>
```

For PUT/PATCH/DELETE HTML forms:

```php
<?= method('DELETE') ?>
```

## Authentication

The included `0001_create_users.php` migration matches the default authentication configuration.

```php
if (login($email, $password)) {
    return redirect('/panel');
}

if (auth()) {
    echo user('name');
}

logout();
```

Protect a route:

```php
get('/panel', 'PanelController@index')->middleware('auth');
```

## Migrations

```bash
php sedo migrate
php sedo migrate:rollback
```

Migration files are plain PHP and receive the native `PDO` connection. There is no schema DSL to learn.

## Shared hosting

SedoPHP supports two deployment styles:

1. **Recommended:** point the domain document root to the project's `public/` directory.
2. **cPanel fallback:** place the whole project in the domain root. The included root `.htaccess` blocks framework internals and internally routes public traffic into `public/`.

No Node.js, Redis, worker, daemon or SSH process is required. See [Shared Hosting](docs/shared-hosting.md).

## CLI

```bash
php sedo serve
php sedo make:controller UserController
php sedo make:model User
php sedo migrate
php sedo migrate:rollback
php sedo version
```

Everything the CLI creates can also be created by hand.

## Tests

```bash
php tests/run.php
```

The GitHub Actions workflow tests PHP 8.3 and 8.4 with SQLite.

## Documentation

- [Getting started](docs/getting-started.md)
- [Core API](docs/api.md)
- [Shared hosting](docs/shared-hosting.md)
- [Security policy](SECURITY.md)

## Design rules

SedoPHP follows a few deliberately strict rules:

1. A PHP developer should understand application code without learning a new language.
2. Prefer one obvious way to do a common task.
3. Make hidden behavior rare and documented.
4. Shared hosting is a first-class target, not an afterthought.
5. Native PHP and PDO remain reachable when the framework abstraction is not enough.
6. Features do not enter the core merely because larger frameworks have them.

## License

MIT.
