# Changelog

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
