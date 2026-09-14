# Shared-hosting verification checklist

The automated test suite checks routing, Composer-free autoloading, `.htaccess` safety rules, SQLite, MariaDB and the built-in PHP development server.

A real hosting account must still be verified before a stable release because cPanel, LiteSpeed and Apache configurations differ between providers.

Run this checklist on each target host:

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

Do not mark a SedoPHP version stable until at least one Apache/cPanel-style host and one LiteSpeed/cPanel-style host have passed this checklist.
