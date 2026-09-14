# Shared hosting deployment

Shared hosting is a primary SedoPHP target.

## Requirements

In cPanel/Plesk select PHP 8.3 or newer and enable PDO plus `pdo_mysql` for MySQL/MariaDB.

SedoPHP does **not** require:

- a long-running PHP process
- Node.js
- Redis
- Docker
- a queue worker
- a WebSocket server
- Composer on the production server

## Option A — document root points to `public/` (recommended)

Upload the project outside the public document root if your host allows it, then set the domain's document root to:

```text
/path/to/project/public
```

Only `public/` is directly web-accessible.

## Option B — ordinary `public_html` hosting

If the host does not let you change the document root, upload the complete project into the site's directory, for example:

```text
public_html/
  .htaccess
  app/
  bootstrap/
  config/
  public/
  routes/
  src/
  storage/
  ...
```

The root `.htaccess` blocks framework directories and sensitive files, then internally routes requests to `public/`.

This fallback requires Apache rewrite support, which is standard on most cPanel hosting.

## Composer-free deployment

SedoPHP first looks for:

```text
vendor/autoload.php
```

When it is absent, `bootstrap/autoload.php` registers the two namespaces used by the project:

```text
SedoPHP\ -> src/
App\     -> app/
```

That means a normal ZIP upload works even if Composer is unavailable on the server.

If you add third-party Composer packages, build `vendor/` locally and upload it with the project.

## File permissions

PHP needs write access to:

```text
storage/logs/
storage/cache/
```

Typical permissions are `755` for directories when PHP runs as your account user. Do not blindly use `777`.

## Subdirectory installation

If the application is served at `https://example.com/shop`, set:

```env
APP_URL=https://example.com/shop
APP_BASE_PATH=shop
```

## Production checklist

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

Also:

- use HTTPS;
- keep `.env` out of Git;
- use a dedicated database user;
- choose a strong database password;
- keep the project on a supported PHP version;
- apply `csrf` middleware to browser forms that change state.
