# Changelog

## Unreleased — 0.3.0-dev

### Architecture and planning

- defined the 0.3 development contract around plain PHP, shared hosting and dependency-free runtime defaults
- documented the 0.3 milestone plan and stable-release gates
- explicitly kept Composer optional on production servers
- documented that Node.js, Redis, external services and permanent daemons will not become core runtime requirements

### Database and models

- added hasOne and belongsToMany relationships with pivot metadata and pivot attach/detach/sync operations
- added nested and constrained eager loading
- added opt-in soft deletes with withTrashed, onlyTrashed, restore and forceDelete
- added upsert, updateOrInsert, firstOrCreate and firstOrNew query helpers
- added chunk and cursor iteration for bounded result processing
- added column comparisons and EXISTS/NOT EXISTS subqueries
- added nested transaction savepoints and opt-in retry attempts for retryable transaction failures
- added schema helpers for soft deletes, column renames, index removal and foreign keys
- enabled SQLite foreign-key enforcement by default with an explicit configuration opt-out

### Testing and test data

- added plain-PHP seeders with make:seeder and db:seed CLI commands
- added dependency-free model factories and a make:factory generator
- added direct Router-based HTTP testing with status, header, body, JSON-path and redirect assertions
- added database row presence and absence assertions
- registered seeder and factory namespaces in both Composer and the fallback autoloader

### Routing, requests and authentication

- added named routes and URL generation with encoded route parameters
- added HMAC-signed URLs, optional expiration and a signed-route middleware
- added APP_KEY configuration for URL signatures
- added optional FormRequest validation classes while retaining the existing validate() helper
- added cache-backed hashed one-time tokens for password reset and email verification
- added single-use token consumption and explicit revocation
- added configurable session-login throttling using the existing rate limiter
- added password reset support without introducing an authentication UI or external identity dependency

### CLI and developer experience

- added generators for middleware, FormRequest classes, jobs and application console commands
- added convention-based application console commands without a service-provider or DI requirement
- added cache:clear and redacted config:show commands
- added routes as a compatible alias for route:list and displayed route names in route listings
- added optional optimize and optimize:clear commands
- added compiled plain-PHP configuration cache and a route metadata manifest
- extended linting to include the root sedo CLI executable

### Production diagnostics and logging

- added configurable text or JSON structured logging with log levels
- added recursive redaction for common sensitive logging context keys
- added request IDs across request attributes, response headers, logs and framework error responses
- added opt-in database query timing and slow-query diagnostics
- query diagnostics record SQL placeholders and binding counts without logging binding values
- kept database diagnostics disabled by default for low production overhead

### Cache, queue and scheduler infrastructure

- added a cache driver contract with the dependency-free file driver as the default
- added a queue driver contract with the database driver as the default
- added unique queued jobs with database-level uniqueness protection
- added opt-in exponential retry backoff while preserving linear backoff as the default
- added stable scheduler task names and before/after/onSuccess/onFailure lifecycle hooks
- added scheduler execution result and duration logging
- kept cron-based one-shot queue and scheduler execution fully supported

### Events, filesystem and HTTP

- added a minimal synchronous event dispatcher for object and named events
- added a local filesystem driver with traversal protection and a replaceable filesystem contract
- added storage() helper access without introducing cloud SDK dependencies
- added a small outbound HTTP client with fluent headers, timeouts, bearer auth, form and JSON requests
- added automatic cURL usage with a native PHP stream fallback
- kept ext-curl optional and disabled automatic redirect following

### Documentation and project metadata

- added Zenodo DOI badge and DOI citation metadata
- reorganized README around installation, features, deployment, security and citation
- updated the 0.2 security/support policy
- expanded contribution guidance with cross-platform test commands
- added a project code of conduct

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
