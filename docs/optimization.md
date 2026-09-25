# Optimization — 0.3 development

Optimization is optional. An application behaves normally when no optimization cache exists.

This is important for shared hosting: deploying a ZIP and editing `.env` remains a valid installation path even when shell access is unavailable.

## Build caches

```bash
php sedo optimize
```

The command currently writes two plain-PHP cache files under `bootstrap/cache/`:

- `config.php` — resolved application configuration;
- `routes.php` — route metadata for diagnostics and tooling.

The configuration cache is loaded automatically on subsequent application boots.

The route manifest does not replace normal route registration in 0.3. This avoids serializing closures or adding hidden routing behavior.

## Clear caches

```bash
php sedo optimize:clear
```

After changing environment or configuration values on an optimized deployment, rebuild or clear the optimization cache.

## Shared-hosting behavior

Optimization never changes the minimum requirements:

- no Composer requirement on the production server;
- no Node.js;
- no Redis;
- no daemon;
- no extra PHP package.

The generated files are excluded from Git and can be safely recreated on each deployment when CLI access is available.
