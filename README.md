# SedoPHP

**Plain PHP. Framework power.**

[![Tests](https://github.com/sedatyildiznet/sedophp/actions/workflows/tests.yml/badge.svg)](https://github.com/sedatyildiznet/sedophp/actions/workflows/tests.yml)
[![Latest Release](https://img.shields.io/github/v/release/sedatyildiznet/sedophp)](https://github.com/sedatyildiznet/sedophp/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/github/license/sedatyildiznet/sedophp)](LICENSE)
[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.22766421.svg)](https://doi.org/10.5281/zenodo.22766421)

SedoPHP is a lightweight PHP 8.3+ framework built for **shared hosting, readable code and predictable behavior**. It keeps the familiar parts of plain PHP while providing routing, database tools, validation, authentication, APIs, queues and scheduling without requiring a large runtime dependency stack.

> Current stable release: **0.3.0**

## Why SedoPHP?

- **Shared-hosting first** — designed for ordinary cPanel, Apache and LiteSpeed environments.
- **Zero third-party runtime dependencies** — Composer is supported but is not required on the production server.
- **Plain PHP views** — no proprietary template language.
- **Small, readable API** — helpers such as `get()`, `input()`, `db()`, `view()` and `auth()`.
- **Modern application features** — route groups, middleware, validation, models, API tokens, JWT, SMTP, queues and scheduler.
- **Safe defaults** — prepared statements, CSRF protection, guarded destructive queries, secure uploads and production-safe error responses.
- **Portable** — PHP 8.3+, MySQL/MariaDB and SQLite support.
- **Tested** — automated CI on PHP 8.3, 8.4 and 8.5 plus MariaDB 11.

## Requirements

- PHP **8.3 or newer**
- PDO extension
- Fileinfo extension
- MySQL/MariaDB or SQLite
- Apache/LiteSpeed, or PHP's built-in server for local development

Node.js, Redis and permanent worker daemons are not required.

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

If `vendor/autoload.php` is missing, SedoPHP uses its own small PSR-4-compatible fallback autoloader.

For a production/shared-hosting installation, see [Shared hosting](docs/shared-hosting.md).

## A small example

### Routes

```php
get('/', fn () => view('home'));

get('/users/{id}', 'UserController@show');

post('/login', 'AuthController@login')->middleware('csrf');

route_group(['prefix' => '/api/v1', 'middleware' => 'token'], function () {
    get('/me', 'ApiController@me');
});
```

### Database

```php
$users = db('users')
    ->where('active', 1)
    ->whereIn('role', ['admin', 'editor'])
    ->orderBy('name')
    ->paginate(20);

$id = db('users')->insert([
    'name' => 'Sedat',
    'email' => 'sedat@example.com',
]);
```

### Model

```php
final class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'settings',
        'active',
    ];

    protected array $casts = [
        'settings' => 'array',
        'active' => 'boolean',
    ];

    protected bool $timestamps = true;
}
```

### Validation

```php
$errors = validate(input_all(), [
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'profile.website' => 'nullable|url',
    'items.*.name' => 'required|string',
]);
```

## Included features

| Area | Included |
| --- | --- |
| Routing | Parameters, groups, middleware, named routes, signed URLs, HEAD/OPTIONS, 404/405 |
| Requests | Form/JSON input, uploads, request IDs, optional FormRequest validation |
| Responses | Views, redirects, JSON, CORS, security headers |
| Database | PDO, query builder, joins, grouping, pagination, upsert, chunk/cursor, nested transactions |
| Models | Fillable fields, casts, timestamps, relations, pivot data, eager loading, soft deletes |
| Validation | Nested paths, wildcards, database rules, file/image rules |
| Security | Sessions, CSRF, auth, login throttling, one-time auth tokens, guarded writes |
| APIs | API tokens, abilities, JWT, rotating refresh tokens, rate limiting |
| Mail | Native PHP mail and SMTP, CC/BCC, attachments |
| Cache | Replaceable driver contract with dependency-free file cache by default |
| Queue | Replaceable driver contract, database queue, unique jobs, linear/exponential retries |
| Scheduler | Cron/timezones, named tasks, lifecycle hooks, overlap protection |
| Events | Minimal synchronous object and named event dispatcher |
| Filesystem | Replaceable storage contract with safe local driver |
| HTTP client | cURL when available with native PHP stream fallback |
| Observability | Structured logs, request IDs, slow-query diagnostics |
| CLI | Generators, migrations, seeders, custom commands, optimize, routes, doctor |

See the documentation section below for the 0.3 APIs and the [0.2 → 0.3 upgrade guide](docs/upgrading-0.2-to-0.3.md).

## Shared hosting

SedoPHP supports two deployment styles:

1. **Recommended:** point the domain document root to the project's `public/` directory.
2. **Fallback:** upload the project to `public_html`; the root `.htaccess` protects framework internals and routes public traffic into `public/`.

The CLI is useful but never mandatory. Queue workers and scheduled tasks can be invoked through cPanel Cron when needed.

Deployment guides:

- [Shared hosting guide](docs/shared-hosting.md)
- [Shared-hosting verification checklist](docs/shared-hosting-checklist.md)

## CLI

```bash
php sedo serve
php sedo make:controller UserController
php sedo make:model User
php sedo make:migration create_posts
php sedo make:seeder UserSeeder
php sedo make:factory UserFactory
php sedo migrate
php sedo db:seed
php sedo migrate:rollback
php sedo queue:work 20 default
php sedo queue:failed
php sedo queue:retry all
php sedo schedule:run
php sedo route:list
php sedo optimize
php sedo optimize:clear
php sedo doctor
php sedo version
```

Everything generated by the CLI can also be created manually.

## Testing

```bash
composer lint
composer test
composer benchmark
```

GitHub Actions currently verifies:

- PHP 8.3 / SQLite
- PHP 8.4 / SQLite
- PHP 8.5 / SQLite
- PHP 8.3 / MariaDB 11
- migrations and rollback
- routing and HTTP behavior
- authentication and CSRF
- query builder and models
- uploads and validation
- Composer and Composer-free autoloading
- shared-hosting protection rules
- CORS and security middleware
- JWT/API tokens, queues, scheduler and SMTP

## Documentation

- [Getting started](docs/getting-started.md)
- [Core API](docs/api.md)
- [Database](docs/database.md)
- [Models](docs/models.md)
- [Authentication](docs/authentication.md)
- [Testing](docs/testing.md)
- [Console](docs/console.md)
- [Logging and diagnostics](docs/logging.md)
- [Cache, queue and scheduler](docs/background-work.md)
- [Events](docs/events.md)
- [Filesystem](docs/filesystem.md)
- [HTTP client](docs/http-client.md)
- [Optimization](docs/optimization.md)
- [Environment doctor](docs/doctor.md)
- [0.2 → 0.3 upgrade guide](docs/upgrading-0.2-to-0.3.md)
- [Complete application example](examples/complete-app/README.md)
- [Advanced 0.2 features](docs/advanced-features.md)
- [Architecture](docs/architecture.md)
- [Shared hosting](docs/shared-hosting.md)
- [Security policy](SECURITY.md)
- [Contributing](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](CHANGELOG.md)

## Design principles

1. Application code should remain understandable to a PHP developer without learning a new language.
2. Prefer one obvious way to perform common tasks.
3. Keep hidden behavior rare and documented.
4. Treat shared hosting as a first-class deployment target.
5. Keep native PHP and PDO reachable when framework abstractions are not enough.
6. Add features to the core only when they fit the framework's scope.

## Stability

SedoPHP **0.3.0** is the current stable release. Patch releases may contain bug fixes, security fixes and documentation/metadata improvements without intentionally breaking documented 0.3 APIs.

Provider-specific Apache, LiteSpeed and cPanel behavior can differ. Run `php sedo doctor` when terminal access is available and use the deployment checklist on new hosting environments.

## Security

SedoPHP has automated security-oriented regression coverage but has **not** undergone an independent third-party security audit.

Please report vulnerabilities privately through GitHub Security Advisories. See [SECURITY.md](SECURITY.md).

## Contributing

Small, focused and readable pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a change.

## Citation

Citation metadata for SedoPHP 0.3.0 is available through [CITATION.cff](CITATION.cff) and GitHub's **Cite this repository** interface.

The existing Zenodo archive for SedoPHP 0.2.1 remains available at DOI **10.5281/zenodo.22766421**. The 0.3.0 archive metadata should be updated after the GitHub release is ingested by Zenodo.

Author: **Sedat Yıldız** — [ORCID 0009-0002-5777-1669](https://orcid.org/0009-0002-5777-1669)

## License

SedoPHP is open-source software licensed under the [MIT License](LICENSE).
