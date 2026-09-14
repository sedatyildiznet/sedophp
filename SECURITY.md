# Security

SedoPHP is currently a development preview. Do not describe 0.1.0-dev as security-audited.

## Built-in protections

- PDO prepared statements for query values
- SQL table/column identifier validation in the query builder
- destructive query guard: `update()` and `delete()` require a `where()` clause
- CSRF token helper and middleware
- HTML escaping helper (`e()`)
- session ID regeneration on login/logout
- `HttpOnly` and `SameSite=Lax` session cookies by default
- PHP `password_hash()` / `password_verify()` authentication
- production error pages that do not expose stack traces when `APP_DEBUG=false`
- root Apache rules that block framework internals in shared-hosting fallback mode

## Reporting a vulnerability

Please open a GitHub security advisory for the repository when available. Avoid publishing working exploit details in a public issue before a fix is available.
