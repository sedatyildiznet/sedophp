# SedoPHP

**Plain PHP. Framework power.**

SedoPHP is a small PHP 8.3+ framework designed around readable PHP, shared hosting, predictable behavior and almost no setup. It deliberately avoids a large dependency tree, template-language lock-in and hidden application magic.

> Status: **0.1.0 stable**

## What makes it different?

- PHP 8.3+
- Works on ordinary cPanel/shared hosting
- Zero third-party runtime dependencies
- Composer-compatible, but Composer is not required on the server
- Plain PHP views
- Small functional API: `get()`, `input()`, `db()`, `view()`, `auth()`...
- PDO prepared statements
- Lightweight models with explicit mass-assignment fields
- Sessions, CSRF, validation, uploads and simple authentication included
- JSON requests/responses for APIs
- CLI is useful, never mandatory
- PSR-4 project layout and PSR-12-oriented source style
- Automated tests on PHP 8.3, 8.4 and 8.5 plus MariaDB 11

## Quick start

```bash
git clone https://github.com/sedatyildiznet/sedophp.git mysite
cd mysite
cp .env.example .env
php sedo serve
```

Open `http://127.0.0.1:8000`.

Composer is optional:

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

The router handles route parameters, HEAD, OPTIONS, 404 and 405 responses. Duplicate method/path registrations are rejected.

## Input

```php
$email = input('email');
$data = input_all();
$file = upload('avatar');
```

JSON request bodies are read automatically. Invalid JSON returns HTTP 400. URL-encoded PUT/PATCH/DELETE bodies are parsed as well.

## Database

```php
$users = db('users')
    ->where('active', 1)
    ->whereIn('role', ['admin', 'editor'])
    ->orderBy('name')
    ->get();

$user = db('users')->where('id', 5)->first();
$name = db('users')->where('id', 5)->value('name');
$names = db('users')->pluck('name');

$id = db('users')->insert([
    'name' => 'Sedat',
    'email' => 'sedat@example.com',
]);
```

`where('column', null)` becomes `IS NULL` automatically. Updates and deletes require a `where()` clause.

## Models

```php
final class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
}
```

Models refuse mass assignment until `$fillable` is explicitly defined.

## Validation

```php
$errors = validate(input_all(), [
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'age' => 'nullable|integer|min:18',
]);
```

Database, file and image rules are available without a separate validation package.

## Uploads

```php
$avatar = upload('avatar');

$errors = validate(['avatar' => $avatar], [
    'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
]);

if (!$errors && $avatar) {
    $path = $avatar->save('storage/uploads', allowedMimes: [
        'image/jpeg',
        'image/png',
    ]);
}
```

Uploads use random filenames by default and block executable PHP-style extensions.

## Session and CSRF

```php
session_set('theme', 'dark');
$theme = session('theme');
flash('success', 'Saved.');
```

```php
post('/profile', 'ProfileController@save')->middleware('csrf');
```

```php
<form method="post" action="/profile">
    <?= csrf() ?>
</form>
```

## Authentication

```php
if (login($email, $password)) {
    return redirect('/panel');
}

if (auth()) {
    echo user('name');
}

logout();
```

Password hashes are never returned by `user()`. Authentication also confirms that the session user still exists in the database.

## Migrations

```bash
php sedo make:migration create_posts
php sedo migrate
php sedo migrate:rollback
```

Migrations use plain PDO and SQL. SedoPHP avoids wrapping MySQL/MariaDB schema DDL in unsafe fake transactions.

## Shared hosting

Two deployment styles are supported:

1. **Recommended:** point the domain document root to the project's `public/` directory.
2. **Fallback:** upload the whole project to `public_html`; the root `.htaccess` blocks framework internals and routes public traffic into `public/`.

No Node.js, Redis, worker, daemon or production Composer process is required.

See [Shared Hosting](docs/shared-hosting.md) and the [verification checklist](docs/shared-hosting-checklist.md).

## CLI

```bash
php sedo serve
php sedo make:controller UserController
php sedo make:model User
php sedo make:migration create_posts
php sedo migrate
php sedo migrate:rollback
php sedo route:list
php sedo doctor
php sedo version
```

Everything created by the CLI can also be created by hand.

## Tests

```bash
composer lint
composer test
```

The lint command is implemented in PHP and works on Windows, Linux and macOS.

GitHub Actions currently checks:

- PHP 8.3 / SQLite
- PHP 8.4 / SQLite
- PHP 8.5 / SQLite
- PHP 8.3 / MariaDB 11
- migrations and rollback
- routing and HTTP smoke behavior
- authentication and CSRF
- query builder and models
- uploads and validation
- Composer metadata
- Composer-free autoloading
- shared-hosting protection rules
- edge cases for relative SQLite paths, same-origin redirects and required validation

## Documentation

- [Getting started](docs/getting-started.md)
- [Core API](docs/api.md)
- [Architecture](docs/architecture.md)
- [Shared hosting](docs/shared-hosting.md)
- [Shared-hosting verification checklist](docs/shared-hosting-checklist.md)
- [Security policy](SECURITY.md)

## Design rules

1. A PHP developer should understand application code without learning a new language.
2. Prefer one obvious way to do a common task.
3. Make hidden behavior rare and documented.
4. Shared hosting is a first-class target, not an afterthought.
5. Native PHP and PDO remain reachable when the framework abstraction is not enough.
6. Features do not enter the core merely because larger frameworks have them.

## Stability

SedoPHP 0.1.0 is the first stable release of the current public API. Patch releases may fix bugs and security issues without intentionally breaking documented 0.1 APIs.

Provider-specific Apache, LiteSpeed and cPanel configurations can still differ. Use `php sedo doctor` and the shared-hosting verification checklist when deploying to a new provider.

## License

MIT.


## 0.2 development features

The current development branch adds pagination, hasMany/belongsTo model relations, file cache, rate limiting, hashed database API tokens, HS256 JWT authentication, SMTP mail, a database queue and a shared-hosting-friendly scheduler.

These features remain dependency-light: Redis, Node.js and permanent worker daemons are not required. Queue workers and scheduled tasks can be invoked from cPanel Cron.

See [Advanced 0.2 features](docs/advanced-features.md).
