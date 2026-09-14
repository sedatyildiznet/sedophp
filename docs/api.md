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

Each returns a route, so middleware can be attached:

```php
post('/account', 'AccountController@save')->middleware('auth', 'csrf');
```

Built-in middleware aliases: `auth`, `guest`, `csrf`.

## Request

```php
input('name', 'default')
input_all()
request()->method()
request()->path()
request()->query('page')
request()->header('authorization')
request()->file('avatar')
request()->expectsJson()
```

## Response

```php
response('Hello')
response('Created', 201)
json(['ok' => true])
redirect('/login')
back()
view('home', ['name' => 'Sedat'])
```

## Database

```php
db('users')->get()
db('users')->select('id', 'name')->get()
db('users')->where('id', 1)->first()
db('users')->where('age', '>=', 18)->get()
db('users')->orWhere('role', 'admin')->get()
db('users')->whereNull('deleted_at')->get()
db('users')->whereNotNull('email')->get()
db('users')->orderBy('name', 'desc')->limit(20)->offset(20)->get()
db('users')->count()
db('users')->insert([...])
db('users')->where('id', 1)->update([...])
db('users')->where('id', 1)->delete()
```

Table and column names are validated. Values are bound through PDO prepared statements. `update()` and `delete()` refuse to run without a `where` condition.

Native PDO is always available:

```php
$pdo = \SedoPHP\Database\Database::pdo();
```

Transactions:

```php
transaction(function (PDO $db) {
    // ...
});
```

## Validation

```php
$errors = validate($data, [
    'name' => 'required|string|max:120',
    'email' => 'required|email',
    'age' => 'nullable|integer',
]);
```

Rules in 0.1: `required`, `nullable`, `string`, `integer`, `boolean`, `email`, `url`, `min`, `max`, `same`, `confirmed`, `in`.

## Session

```php
session('key', $default)
session_set('key', $value)
session_forget('key')
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

## Authentication

```php
login($identity, $password)
logout()
auth()
user()
user('email')
```

Authentication uses PHP's `password_verify()` and rehashes passwords when `PASSWORD_DEFAULT` changes.

## Utilities

```php
e($value)
url('/path')
log_info('message', ['context' => 'value'])
log_error('message')
config('app.name')
env('APP_ENV')
```
