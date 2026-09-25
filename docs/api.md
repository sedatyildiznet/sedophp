# SedoPHP core API

The public API is intentionally small.

## Routing

```php
get($path, $action)
post($path, $action)
put($path, $action)
patch($path, $action)
delete($path, $action)
```

Middleware:

```php
post('/account', 'AccountController@save')->middleware('auth', 'csrf');
```

Named routes:

```php
get('/users/{id}', 'UserController@show')->name('users.show');

route('users.show', ['id' => 5]);
signed_route('users.show', ['id' => 5], '+30 minutes');
```

Built-in aliases include `auth`, `guest`, `csrf` and, in 0.3 development, `signed`.

The router automatically handles HEAD and OPTIONS, returns 405 with an `Allow` header for wrong methods, and rejects duplicate method/path registrations.

## Request

```php
input('name', 'default')
input_all()
request()->method()
request()->path()
request()->query('page')
request()->header('authorization')
upload('avatar')
```

Malformed JSON returns HTTP 400.

## Response

```php
response('Hello')
response('Created', 201)
json(['ok' => true])
redirect('/login')
back()
view('home', ['name' => 'Sedat'])
```

`back()` refuses cross-host Referer redirects.

## Database

```php
db('users')->get()
db('users')->select('id', 'name')->get()
db('users')->where('id', 1)->first()
db('users')->where('deleted_at', null)->get()
db('users')->whereIn('id', [1, 2, 3])->get()
db('users')->whereNotIn('role', ['blocked'])->get()
db('users')->orderBy('name', 'desc')->limit(20)->offset(20)->get()
db('users')->value('email')
db('users')->pluck('name')
db('users')->exists()
db('users')->count()
db('users')->insert([...])
db('users')->where('id', 1)->update([...])
db('users')->where('id', 1)->delete()
```

Values use PDO prepared statements. Table and column identifiers are validated. Update and delete refuse to run without a WHERE condition.

Native PDO:

```php
$pdo = \SedoPHP\Database\Database::pdo();
```

Transactions:

```php
transaction(function (PDO $db) {
    // ...
});
```

## Models

Model writes require explicit `$fillable` fields.

```php
final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
}
```

For unrestricted explicit writes use `db()`.

## Validation

Rules in the stabilization preview:

`required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `date`, `min`, `max`, `size`, `same`, `confirmed`, `in`, `regex`, `unique`, `exists`, `file`, `image`, `mimes`.

Database rules:

```php
'email' => 'unique:users,email'
'user_id' => 'exists:users,id'
```

For regex patterns containing `|`, pass rules as an array so the pipe is not interpreted as a rule separator.

For uploaded files, `min`, `max` and `size` are measured in KiB.

Optional request classes can extend `SedoPHP\Http\FormRequest` and expose `passes()`, `fails()`, `errors()` and `validated()` while keeping the original `validate()` helper available.

## Uploads

```php
$file = upload('avatar');

if ($file) {
    $path = $file->save(
        'storage/uploads',
        allowedMimes: ['image/jpeg', 'image/png'],
        maxBytes: 2 * 1024 * 1024,
    );
}
```

Useful methods:

```php
$file->isValid()
$file->originalName()
$file->mimeType()
$file->extension()
$file->size()
$file->isImage()
```

Executable PHP-style extensions are blocked by `save()`.

## Session

```php
session('key', $default)
session_set('key', $value)
session_forget('key')
session_pull('key', $default)
flash('message', 'Saved')
```

## CSRF

```php
csrf_token()
csrf()
method('PUT')
method('PATCH')
method('DELETE')
```

Tokens rotate on login and logout.

## Authentication

```php
login($identity, $password)
logout()
auth()
user()
user('email')
```

`user()` never exposes the configured password column.

0.3 development also includes login throttling, single-use password-reset tokens and email-verification tokens. See [authentication.md](authentication.md).

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

## Utilities

```php
e($value)
url('/path')
log_info('message', ['context' => 'value'])
log_error('message')
config('app.name')
env('APP_ENV')
```


## 0.2 development APIs

Additional APIs are documented in [advanced-features.md](advanced-features.md).

Built-in middleware aliases now also include throttle, token and jwt. Parameterized middleware uses the form throttle:60,60.

Additional helpers include cache_get(), cache_put(), cache_remember(), cache_forget(), jwt_encode(), jwt_decode(), jwt_claim(), api_token_issue(), api_token_revoke(), token_can(), rate_limit(), mail_send(), queue_push() and queue_work().

Additional CLI commands:

~~~bash
php sedo queue:work 20
php sedo schedule:run
~~~
