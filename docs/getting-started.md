# Getting started

## Requirements

- PHP 8.3 or newer
- PDO
- A PDO driver for your database (`pdo_mysql` for MySQL/MariaDB)
- Apache with `mod_rewrite` for the included `.htaccess` setup, or an equivalent Nginx rule

Composer is recommended but not required at runtime.

## Installation

Clone or download the repository and copy `.env.example` to `.env`.

```bash
cp .env.example .env
```

Configure the application URL and database:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=example
DB_USER=example_user
DB_PASS=change-me
```

Never commit `.env`.

## Application flow

Every web request enters `public/index.php`:

```text
Request
  -> Application
  -> Router
  -> Middleware
  -> Controller / closure
  -> Response
```

There are no service providers or application containers to understand before writing a page.

## Add a page

`routes/web.php`:

```php
get('/about', function () {
    return view('about', ['title' => 'About']);
});
```

`app/Views/about.php`:

```php
<h1><?= e($title) ?></h1>
```

`e()` should be used for untrusted values written into HTML.

## Add a controller

```bash
php sedo make:controller ProductController
```

Or create `app/Controllers/ProductController.php` yourself:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

final class ProductController
{
    public function show(string $id)
    {
        $product = db('products')->where('id', $id)->first();
        return $product ? view('products.show', compact('product')) : response('Not found', 404);
    }
}
```

Then:

```php
get('/products/{id}', 'ProductController@show');
```

Route parameters are passed to the action in their path order. Request data is intentionally accessed with `input()` rather than automatic dependency injection.

## Database and migrations

The default project includes a users migration for the built-in authentication helper.

```bash
php sedo migrate
```

A migration is plain PHP:

```php
return [
    'up' => function (PDO $db): void {
        $db->exec('CREATE TABLE posts (...)');
    },
    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS posts');
    },
];
```

This is intentional: SQL remains SQL.

## Production

Set:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

Use HTTPS, point the document root to `public/` when your hosting panel allows it, and make `storage/` writable by PHP.
