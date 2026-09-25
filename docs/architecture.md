# Architecture

SedoPHP intentionally ships as a single repository during the 0.x series.

The repository contains both:

- the small framework core under `src/`;
- the starter application under `app/`, `routes/`, `config/` and `public/`.

This is deliberate. A single downloadable ZIP is easier to understand and deploy on ordinary shared hosting, including servers where Composer is not installed.

## Package strategy

The Composer package is currently named `sedophp/framework` and is a `project` package because the repository is directly usable as an application skeleton.

Before 1.0, the project may be split into separate core and starter packages only if that produces a clear user benefit without harming the Composer-free deployment path.

Until then, keeping the distribution in one repository is considered a feature rather than technical debt.

## Request lifecycle

```text
public/index.php
  -> bootstrap/app.php
  -> Application
  -> Request
  -> Router
  -> Middleware
  -> Controller / Closure
  -> Response
```

There is no service-provider layer and no dependency-injection container required for ordinary application code.

## 0.3 development contract

SedoPHP 0.3 expands developer experience and production readiness without changing the framework's deployment model.

The following are release constraints, not optional preferences:

1. PHP 8.3+ remains the only runtime language requirement.
2. Production servers do not need Composer when the repository or release ZIP is deployed with the built-in fallback autoloader.
3. Node.js, npm, Redis, long-running daemons and external services are never required to boot an application.
4. Apache/LiteSpeed shared hosting remains a first-class target.
5. SQLite and MySQL/MariaDB remain supported database targets.
6. Plain PHP views remain the default; SedoPHP does not introduce a proprietary template language.
7. PDO and native PHP APIs remain reachable when framework abstractions are not enough.
8. New cache, queue, filesystem and similar integrations must be replaceable through small interfaces while retaining dependency-free default drivers.
9. CLI features improve development and operations but must not become mandatory for serving HTTP requests.
10. Existing documented 0.2 APIs should remain compatible unless a change fixes a correctness or security issue and is documented in the upgrade guide.

## Features intentionally outside the 0.3 core

The following are intentionally not part of the 0.3 core:

- a mandatory dependency-injection container;
- service-provider bootstrapping;
- a proprietary template engine;
- a frontend asset pipeline;
- mandatory Redis or another network cache;
- mandatory queue or scheduler daemons;
- framework-owned cloud SDKs;
- large third-party runtime dependency stacks.

Applications may use any of these independently when needed.

## Extension strategy

Where 0.3 adds replaceable infrastructure, SedoPHP should provide a small contract and one dependency-free default implementation.

Examples:

```text
CacheDriverInterface      -> file driver
QueueDriverInterface      -> database driver
FilesystemDriverInterface -> local filesystem driver
```

Optional integrations belong in application code or separate packages unless they are broadly useful and can preserve SedoPHP's deployment constraints.

## Release gates

A feature is complete only when all applicable gates pass:

- syntax/lint checks;
- automated regression tests;
- PHP 8.3, 8.4 and 8.5 SQLite CI;
- PHP 8.3 MariaDB CI;
- Composer and Composer-free autoload coverage;
- shared-hosting deployment compatibility;
- documentation and examples;
- upgrade notes when behavior changes.

## Design constraints

1. Native PHP should remain visible.
2. Public APIs should stay small.
3. Hidden behavior must be rare and documented.
4. Shared hosting must remain a first-class target.
5. Core features require tests before release.
6. Prefer capability over ceremony.
7. Prefer a dependency-free default over a mandatory external service.
8. A new feature must not make the minimum deployment path more complicated.
