# Shared-hosting verification checklist

The automated test suite checks routing, Composer-free autoloading, `.htaccess` safety rules, SQLite, MariaDB and the built-in PHP development server.

Real cPanel, LiteSpeed and Apache configurations differ between providers. Use this checklist when deploying SedoPHP to a new hosting environment.

- [ ] PHP 8.3 or newer selected
- [ ] `pdo_mysql` enabled
- [ ] `fileinfo` enabled for upload MIME detection
- [ ] `php sedo doctor` has no critical failures when SSH/Terminal is available
- [ ] root-domain install works with document root pointed at `public/`
- [ ] `public_html` fallback install works with the root `.htaccess`
- [ ] static files under `public/` load directly
- [ ] `.env` cannot be downloaded
- [ ] `storage/`, `src/`, `config/` and `vendor/` cannot be downloaded
- [ ] normal route returns 200
- [ ] unknown route returns 404
- [ ] wrong HTTP method returns 405 with an `Allow` header
- [ ] CSRF-protected form accepts a valid token and rejects an invalid token
- [ ] login/logout works and session persists
- [ ] MySQL/MariaDB migration and rollback work
- [ ] file upload works into a writable non-public directory
- [ ] subdirectory install works with `APP_BASE_PATH`
- [ ] HTTPS session cookies work with `SESSION_SECURE=true`
- [ ] production errors do not expose stack traces

Passing this checklist on a new provider confirms provider-specific deployment behavior; it is not a substitute for the automated test suite.


## Verified deployment

SedoPHP 0.1.0 has been verified on a real shared-hosting account with:

- PHP 8.4.24
- installation in a subdirectory using `APP_BASE_PATH`
- root and public `.htaccess` rewrite flow
- normal application page rendering
- `/health` JSON route
- MySQL connection through `.env`
- successful query against the `users` table

This verifies the basic shared-hosting deployment path. Provider-specific behavior for other Apache/LiteSpeed/cPanel configurations should still be checked with the list above.
