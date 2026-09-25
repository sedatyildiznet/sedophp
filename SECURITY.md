# Security

SedoPHP 0.3.0 is a stable release, but it has **not** undergone an independent third-party security audit. Stable means the documented 0.3 API has passed the project's automated regression and integration test matrix; it does not mean the software is vulnerability-free.

## Supported versions

Security fixes are applied to the current stable 0.3 release line.

| Version | Supported |
| --- | --- |
| 0.3.x | Yes |
| 0.2.x | No |
| 0.1.x | No |

## Built-in protections

- PDO prepared statements for query values
- SQL identifier validation
- destructive-query guard for update/delete without a WHERE clause
- explicit model `$fillable` fields for mass assignment
- CSRF helper, middleware and token rotation
- HTML escaping helper (`e()`)
- session ID regeneration on login/logout
- strict-mode, cookie-only PHP sessions
- HttpOnly and SameSite session cookies by default
- PHP `password_hash()` / `password_verify()`
- password hashes removed from authenticated user data
- session-user existence verification
- malformed JSON rejected with HTTP 400
- same-origin validation for back redirects
- upload executable-extension blocking
- configurable CORS and security headers
- hashed API tokens with abilities and revocation
- strict JWT expiry/issuer validation and token revocation support
- trusted proxy/CIDR support for client-IP-sensitive rate limiting
- production error responses that hide stack traces
- shared-hosting root rules that block framework internals

## Upload guidance

Prefer storing uploads outside `public/`. If a file must be public, restrict MIME types and never execute files from an upload directory.

## Deployment guidance

When terminal access is available, run:

```bash
php sedo doctor
```

Review the [shared-hosting verification checklist](docs/shared-hosting-checklist.md) when deploying to a provider or web-server configuration that has not been tested before.

Keep `.env`, application secrets, database credentials and signing keys outside version control.

## Reporting a vulnerability

Please use a **private GitHub Security Advisory**:

https://github.com/sedatyildiznet/sedophp/security/advisories/new

Include the affected version, reproduction steps, expected impact and any suggested mitigation if known.

Do not publish working exploit details in a public issue before a fix is available.
