# Upgrading from SedoPHP 0.2 to 0.3

SedoPHP 0.3 is designed to preserve documented 0.2 application APIs while adding new optional capabilities.

The deployment model does not change:

- PHP 8.3+;
- Composer optional on production servers;
- no Node.js requirement;
- no Redis requirement;
- no permanent worker daemon requirement;
- Apache/LiteSpeed shared hosting remains supported.

## Before upgrading

1. Back up the application files and database.
2. Commit or archive local application changes.
3. Replace/update the SedoPHP framework files.
4. Clear generated optimization caches if they exist.
5. Run the new database migrations.
6. Run `php sedo doctor` when terminal access is available.
7. Run the application's own tests before switching production traffic.

## Database migration

0.3 adds queue metadata for unique jobs and backoff strategy.

Run:

```bash
php sedo migrate
```

The queue migration is additive. Existing queued jobs continue to use linear backoff.

## Environment additions

The new variables are optional unless the corresponding feature is used.

Recommended additions:

```dotenv
APP_KEY=

LOG_PATH=storage/logs/app.log
LOG_FORMAT=text
LOG_LEVEL=info

DB_FOREIGN_KEYS=true
DB_LOG_QUERIES=false
DB_SLOW_QUERY_MS=0

AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_DECAY_SECONDS=60
AUTH_PASSWORD_RESET_TTL=3600
AUTH_EMAIL_VERIFICATION_TTL=86400

FILESYSTEM_PATH=storage/app
```

### APP_KEY

`APP_KEY` is required for signed URLs.

Existing applications that do not use signed URLs can boot without it, but `php sedo doctor` reports a warning until it is configured.

Use a long random application-specific value and do not commit it to Git.

## Authentication behavior

0.3 adds login throttling using the existing cache-based rate limiter.

The starter configuration defaults to:

```dotenv
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_DECAY_SECONDS=60
```

Set `AUTH_LOGIN_MAX_ATTEMPTS=0` if an application already implements its own login throttling.

Password reset and email verification are new primitives and do not change existing login/session flows unless application code uses them.

## Cache

The existing `Cache` facade API remains compatible.

The implementation is now behind `CacheDriverInterface`, with the file cache still used by default.

No application change is required for normal file-cache usage.

## Queue

Existing calls such as:

```php
Queue::push(MyJob::class, $payload);
queue_push(MyJob::class, $payload);
```

continue to work.

New optional arguments were added after the existing arguments for:

- unique keys;
- linear/exponential backoff strategy.

Existing positional calls remain valid.

## Scheduler

Existing scheduler definitions continue to work.

New optional APIs include:

```php
->name('task.name')
->before(...)
->after(...)
->onSuccess(...)
->onFailure(...)
```

No daemon is introduced. `php sedo schedule:run` remains suitable for cron.

## Models and database APIs

0.3 adds new APIs without removing the existing ones:

- `hasOne`;
- `belongsToMany`;
- soft deletes;
- constrained/nested eager loading;
- upsert and first-or-create helpers;
- chunk/cursor processing;
- nested transaction savepoints;
- additional Schema helpers.

Existing `hasMany`, `belongsTo`, `db()`, model fillable rules and native PDO access remain available.

## Routing

Existing route declarations remain valid.

Named and signed routes are optional:

```php
get('/users/{id}', 'UserController@show')->name('users.show');

route('users.show', ['id' => 5]);
```

## CLI

Existing commands remain available, including `route:list`.

0.3 adds `routes` as an alias and adds generators, seeders, custom commands, optimization and cache/config utilities.

## Optimization cache

If an application used a 0.3 development build or has generated optimization files, clear them after framework/config changes:

```bash
php sedo optimize:clear
php sedo optimize
```

Optimization remains optional.

## Shared-hosting deployment

For ZIP-based deployment without Composer:

1. upload the new SedoPHP files;
2. preserve the application's `.env`;
3. run migrations through terminal if available, or use the application's normal migration deployment process;
4. verify `storage/` permissions;
5. keep the domain document root pointed at `public/` when the host supports it.

The fallback `public_html` deployment remains supported by the root protection rules.

## Breaking changes

The 0.3 release target intentionally avoids breaking documented 0.2 APIs.

If an application depended on undocumented internal classes or private implementation details, review the following internal refactors:

- cache storage now uses a driver contract;
- queue storage now uses a driver contract;
- logging configuration moved into `config/logging.php`;
- filesystem storage is now configured through `config/filesystem.php`.

Use the public helpers/facades where possible.
