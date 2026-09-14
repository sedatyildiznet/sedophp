# Security

SedoPHP is currently a development preview. Do not describe 0.1.0-dev as security-audited.

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
- external Referer values rejected by `back()`
- upload executable-extension blocking
- production error pages hide stack traces
- shared-hosting root rules block framework internals

## Upload guidance

Prefer saving uploads outside `public/`. If a file must be public, restrict MIME types and never execute files from an upload directory.

## Reporting a vulnerability

Use a private GitHub security advisory:

https://github.com/sedatyildiznet/sedophp/security/advisories/new

Avoid publishing working exploit details in a public issue before a fix is available.
