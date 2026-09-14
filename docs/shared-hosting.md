# Shared hosting deployment

Shared hosting is a primary SedoPHP target.

## Requirements

In cPanel/Plesk select PHP 8.3 or newer and enable:

- PDO;
- `pdo_mysql` for MySQL/MariaDB;
- `fileinfo` when using upload MIME checks.

SedoPHP does **not** require:

- a long-running PHP process;
- Node.js;
- Redis;
- Docker;
- a queue worker;
- a WebSocket server;
- Composer on the production server.

## Option A — document root points to `public/` (recommended)

Upload the project outside the public document root if your host allows it, then set the domain's document root to:

```text
/path/to/project/public
```

Only `public/` is directly web-accessible.

## Option B — ordinary `public_html` hosting

If the host does not let you change the document root, upload the complete project into the site's directory:

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

The root `.htaccess` blocks framework directories and sensitive files, then internally routes public traffic into `public/`.

## Composer-free deployment

SedoPHP first looks for `vendor/autoload.php`. When it is absent, `bootstrap/autoload.php` registers:

```text
SedoPHP\ -> src/
App\     -> app/
```

A normal ZIP upload therefore works without Composer. If your application adds third-party Composer packages, build `vendor/` locally and upload it with the project.

## File permissions

PHP needs write access to:

```text
storage/logs/
storage/cache/
```

Use the permissions required by your host. Do not blindly use `777`.

## Subdirectory installation

For `https://example.com/shop`:

```env
APP_URL=https://example.com/shop
APP_BASE_PATH=shop
SESSION_PATH=/shop
```

## Production

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=Lax
```

Run `php sedo doctor` when the hosting account provides SSH or a terminal.

See [Shared-hosting verification checklist](shared-hosting-checklist.md) before marking a release stable.
