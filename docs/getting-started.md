# Getting started

## Requirements

- PHP 8.3 or newer
- PDO
- `pdo_mysql` for MySQL/MariaDB
- `fileinfo` when using MIME-aware uploads
- Apache rewrite support for the included `.htaccess` deployment

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

## Check the environment

When a terminal is available:

```bash
php sedo doctor
```

It checks PHP, PDO, the configured database driver, writable storage, the `.env` file, database connectivity and Apache rewrite availability when detectable.

## Application flow

```text
Request
  -> Application
  -> Router
  -> Middleware
  -> Controller / closure
  -> Response
```

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

## Add a controller

```bash
php sedo make:controller ProductController
```

Or create it by hand. Route parameters are passed in path order; request data is read explicitly with `input()`.

## Database and migrations

```bash
php sedo make:migration create_posts
php sedo migrate
```

Migrations are plain PHP callbacks receiving PDO. On MySQL/MariaDB, schema migrations are intentionally not wrapped in misleading DDL transactions because those engines may implicitly commit schema statements.

## Models

Generated models contain an empty `$fillable` array. Add the fields that may be mass-assigned before using `Model::create()` or model `update()`.

For unrestricted explicit writes, use `db()`.

## Production

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=Lax
```

Use HTTPS, prefer a document root pointed to `public/`, and make `storage/` writable by PHP.
