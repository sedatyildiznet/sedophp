# SedoPHP 0.3 roadmap

Status: **development**

Working version: **0.3.0-dev**

Theme: **Developer Experience & Production Readiness**

The goal of 0.3 is to make SedoPHP more complete for everyday application development without changing its identity: plain PHP, shared-hosting-first deployment, small APIs and zero third-party runtime dependencies.

## Non-negotiable constraints

Every milestone must preserve these rules:

- PHP 8.3+.
- No required third-party runtime packages.
- Composer remains optional on production servers.
- No Node.js/npm requirement.
- No Redis requirement.
- No mandatory long-running worker or scheduler daemon.
- Apache/LiteSpeed shared hosting remains supported.
- MySQL/MariaDB and SQLite remain supported.
- Existing plain-PHP helpers remain available.
- CLI commands remain optional operational conveniences.
- New abstractions must not hide native PHP/PDO escape hatches.

## Milestone M1 — Database and ORM

Planned work:

- add `hasOne`;
- add `belongsToMany` and pivot metadata;
- constrained eager loading;
- nested eager loading improvements;
- soft deletes;
- `upsert()`;
- `updateOrInsert()`;
- `firstOrCreate()` / `firstOrNew()`;
- `chunk()` / `cursor()`;
- `whereExists()` / `whereNotExists()`;
- subquery support where it can remain safe and readable;
- callback transactions;
- nested transactions through savepoints where supported;
- opt-in deadlock retry;
- schema helpers for soft deletes and common index/foreign-key operations.

Acceptance:

- SQLite and MariaDB regression coverage;
- no ORM feature may require Composer;
- destructive operations remain guarded;
- unsupported database behavior fails explicitly instead of silently degrading.

## Milestone M2 — Seeders, factories and application testing

Planned work:

- `make:seeder`;
- `db:seed`;
- lightweight factories without a Faker dependency;
- application HTTP test client;
- response assertions;
- database assertions.

Acceptance:

- testing remains usable without PHPUnit;
- factory helpers use native PHP primitives;
- test APIs do not become runtime dependencies for production requests.

## Milestone M3 — Routing and authentication primitives

Planned work:

- named routes;
- route URL generation;
- signed and expiring URLs;
- signed-route middleware;
- optional Form Request-style validation classes;
- password-reset primitives;
- email-verification primitives;
- login throttling using the existing rate limiter.

Acceptance:

- existing route helpers remain valid;
- signatures use native cryptographic functions;
- authentication remains application-controlled rather than a generated UI stack.

## Milestone M4 — CLI and developer experience

Planned work:

- `make:middleware`;
- `make:request`;
- `make:job`;
- `make:seeder`;
- `make:command`;
- application-defined console commands;
- `cache:clear`;
- `config:show`;
- `db:seed`;
- `optimize` / `optimize:clear`;
- retain existing command aliases for compatibility.

Acceptance:

- web requests never depend on CLI initialization;
- generated files are plain PHP and can be created manually.

## Milestone M5 — Production diagnostics and logging

Planned work:

- structured log context;
- optional JSON logs;
- request IDs shared by responses, logs and error reports;
- development query diagnostics;
- configurable slow-query logging;
- safer diagnostic redaction.

Acceptance:

- diagnostics are disabled or low-cost by default in production;
- sensitive values are not written to logs by default.

## Milestone M6 — Replaceable cache, queue and scheduler infrastructure

Planned work:

- cache driver contract with the current file implementation as default;
- queue driver contract with the database implementation as default;
- unique jobs;
- exponential backoff;
- scheduler task names and lifecycle hooks;
- scheduler execution result/duration logging.

Acceptance:

- file cache and database queue remain fully functional without external services;
- cPanel Cron remains sufficient for queue/scheduler operation.

## Milestone M7 — Events, filesystem and HTTP client

Planned work:

- minimal synchronous event dispatcher;
- local filesystem driver and contract;
- small HTTP client;
- cURL support when available with a native stream fallback or graceful optional capability.

Acceptance:

- no cloud SDK in core;
- `ext-curl` must not become a hard requirement.

## Milestone M8 — Optimization and doctor

Planned work:

- optional route/config metadata caches;
- `sedo optimize`;
- `sedo optimize:clear`;
- expanded `sedo doctor` checks for PHP, PDO, writable paths, database, environment, document root, security, mail, queue and scheduler configuration.

Acceptance:

- an unoptimized application behaves identically;
- applications without terminal access continue to work.

## Milestone M9 — Documentation, examples and benchmarks

Planned work:

- dedicated database/model/testing/console/events/filesystem/HTTP/logging/optimization/authentication guides;
- 0.2 -> 0.3 upgrade guide;
- expanded but small real-world example application;
- local benchmark script for bootstrap, memory, routing and common database operations.

Benchmarks exist to detect regressions inside SedoPHP, not to make unsupported marketing comparisons.

## Release sequence

```text
0.3.0-dev
0.3.0-alpha.1
0.3.0-beta.1
0.3.0-rc.1
0.3.0
```

Before the release candidate, all planned features must be frozen. The RC phase accepts bug fixes, security fixes, documentation corrections and test improvements only.

## Stable release checklist

- finalize `CHANGELOG.md`;
- set `VERSION` to `0.3.0`;
- update README stable-version references;
- update `CITATION.cff` and supported-version metadata;
- pass the full CI matrix;
- pass Composer-free boot tests;
- pass shared-hosting checks with both recommended `public/` document root and protected `public_html` fallback layouts;
- verify migrations and rollbacks on SQLite and MariaDB;
- verify queue and scheduler execution through one-shot CLI invocations suitable for cron;
- run security regression tests;
- install from a clean Git clone;
- install from a clean release ZIP without Composer;
- tag `v0.3.0`;
- publish GitHub release notes;
- archive the release through the existing Zenodo process.

## Progress

- [x] Define 0.3 architectural constraints.
- [x] Create the 0.3 implementation roadmap.
- [x] M1 — Database and ORM.
- [x] M2 — Seeders, factories and application testing.
- [x] M3 — Routing and authentication primitives.
- [x] M4 — CLI and developer experience.
- [x] M5 — Production diagnostics and logging.
- [x] M6 — Cache, queue and scheduler contracts.
- [x] M7 — Events, filesystem and HTTP client.
- [x] M8 — Optimization and doctor.
- [ ] M9 — Documentation, examples and benchmarks.
- [ ] Release candidate regression pass.
- [ ] Stable 0.3.0 release.
