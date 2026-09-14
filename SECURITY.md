# Security

SedoPHP 0.1.0 is a stable release, but it has **not** undergone an independent third-party security audit. Stable means the documented 0.1 API has passed the project's automated regression and integration test matrix; it does not mean the software is vulnerability-free.

## Built-in protections

- PDO prepared statements for query values
- SQL identifier validation
- destructive-query guard for update/delete without WHERE
- explicit model `$fillable` fields for mass assignment
- CSRF helper and middleware
- CSRF token rotation on login/logout
- HTML escaping helper (`e()`)
- session ID regeneration on login/logout
- strict-mode, cookie-only PHP sessions
- HttpOnly and SameSite session cookies by default
- PHP `password_hash()` / `password_verify()`
- password hashes removed from `user()`
- authentication verifies that the session user still exists
- invalid JSON rejected with HTTP 400
- same-origin validation for `back()`
- upload executable-extension blocking
- production error pages hide stack traces
- shared-hosting root rules block framework internals

## Upload guidance

Prefer saving uploads outside `public/`. If a file must be public, restrict MIME types and never execute files from an upload directory.

## Deployment guidance

Run:

```bash
php sedo doctor
```

when terminal access is available. Review the shared-hosting checklist when deploying to a provider or web-server configuration that has not been tested before.

## Reporting a vulnerability

Use a private GitHub security advisory:

https://github.com/sedatyildiznet/sedophp/security/advisories/new

Avoid publishing working exploit details in a public issue before a fix is available.
