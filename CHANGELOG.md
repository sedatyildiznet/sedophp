# Changelog

## 0.2.1 — 2026-09-15

### Documentation and metadata

- added standardized `CITATION.cff` metadata for GitHub's citation interface
- linked SedoPHP citation metadata to Sedat Yıldız's ORCID
- documented the Zenodo/DOI citation path for archived releases
- refreshed release metadata for the 0.2.1 patch

## 0.2.0 — 2026-09-14

### Database and models

- query-builder pagination with metadata
- joins, left/right joins, grouping and HAVING support
- grouped pagination/count support
- hasMany and belongsTo model relations with hydrated results and eager loading
- opt-in model casts and automatic timestamps
- portable Schema/Blueprint migration builder for MySQL/MariaDB and SQLite
- backward-compatible plain-PDO migrations
- database-backed API token and queue migrations

### Routing, security and APIs

- nested route groups with prefixes and group middleware
- global middleware pipeline covering normal, 404, 405 and OPTIONS responses
- dedicated CORS and security-header middleware
- JSON-aware global error responses for APIs
- nested dot-path and wildcard-array validation
- parameterized middleware
- file-backed rate limiting and throttle middleware
- hashed database API tokens with abilities and revocation
- HS256 JWT issuing/validation and jwt middleware
- request attributes for token/JWT context
- strict JWT expiry/issuer validation and orphaned API-token rejection
- rotating JWT refresh tokens and cache-backed revocation
- trusted proxy/CIDR support for safe client IP rate limiting
- configurable CORS and secure response headers

### Application services

- shared reusable test harness and dedicated 0.2 release regression suite
- file cache with TTL, remember, forget, clear and atomic increment
- log, native PHP mail and SMTP mail drivers
- mail CC, BCC, attachments and socket-level SMTP integration tests
- database queue with stale-worker recovery and failed-job operations
- named queues with per-job backoff and timeout limits
- shared-hosting-friendly scheduler with overlap and duplicate-run protection
- cron expressions and per-task timezones
- queue worker, failed-job and scheduler CLI commands

## 0.1.0 — 2026-09-14

First stable SedoPHP release.

### Core

- routing and middleware
- route parameters
- HEAD and OPTIONS support
- 404 and 405 responses
- duplicate-route protection
- request and response helpers
- malformed JSON handling
- URL-encoded PUT/PATCH/DELETE input
- plain PHP views

### Database

- PDO-based MySQL/MariaDB and SQLite support
- prepared query values and validated identifiers
- query builder with NULL, IN, NOT IN, value, pluck and exists helpers
- guarded update/delete operations
- lightweight models with explicit mass-assignment fields
- transactions
- migrations and rollback
- MySQL/MariaDB-safe DDL migration behavior
- project-root resolution for relative SQLite paths

### Security and application features

- sessions and flash data
- strict-mode, cookie-only session defaults
- CSRF middleware and token rotation
- authentication with PHP password hashing
- password hashes excluded from authenticated user data
- session-user existence verification
- validation including database, file and image rules
- safe upload helper with executable-extension blocking
- HTML escaping helper
- same-origin back redirects
- production-safe error pages
- shared-hosting directory protection rules

### Developer experience

- CLI server
- controller/model/migration generators
- route listing
- environment doctor
- Composer and Composer-free autoloading
- cross-platform PHP linting
- documentation and shared-hosting deployment guides

### Test matrix

- PHP 8.3 + SQLite
- PHP 8.4 + SQLite
- PHP 8.5 + SQLite
- PHP 8.3 + MariaDB 11
- migration/rollback integration
- CLI and HTTP smoke tests
- auth, CSRF, validation, upload, query builder and model tests
- edge-case regression tests

Provider-specific hosting behavior may vary; use the shared-hosting verification checklist for new deployment environments.
