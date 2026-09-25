# Logging and diagnostics — 0.3 development

SedoPHP 0.3 keeps production diagnostics dependency-free. Logging uses ordinary files, request correlation uses a small middleware, and database diagnostics are opt-in.

## Logging configuration

```dotenv
LOG_PATH=storage/logs/app.log
LOG_FORMAT=text
LOG_LEVEL=info
```

Supported formats:

- `text` — human-readable line-oriented logs;
- `json` — one JSON object per line for structured ingestion.

Supported levels are `debug`, `info`, `warning` and `error`.

Existing helpers remain valid:

```php
log_info('Payment completed', [
    'order_id' => 42,
]);

log_error('Payment failed', [
    'order_id' => 42,
]);
```

## Structured context and redaction

Context is stored with each log entry.

Common sensitive keys are redacted recursively, including password, secret, authorization, cookie, token, JWT, API-key and database-password style keys.

```php
log_info('Login attempt', [
    'email' => 'user@example.test',
    'password' => 'never-written-to-log',
]);
```

The password value is written as `[REDACTED]`.

Applications should still avoid placing unnecessary secrets in logging context.

## Request IDs

Every HTTP request receives a request ID through the built-in global `request_id` middleware.

The ID is:

- stored on the Request as the `request_id` attribute;
- returned through the `X-Request-Id` response header;
- automatically added to framework log context;
- attached to framework-generated error responses.

A valid incoming `X-Request-Id` is preserved so upstream proxies and applications can correlate the same request. Otherwise SedoPHP generates a cryptographically random identifier.

```php
$id = request()->attribute('request_id');
```

No external tracing service is required.

## Database query diagnostics

Query logging is disabled by default.

```dotenv
DB_LOG_QUERIES=false
DB_SLOW_QUERY_MS=0
```

To log all QueryBuilder statements:

```dotenv
DB_LOG_QUERIES=true
```

To only report slow queries:

```dotenv
DB_LOG_QUERIES=false
DB_SLOW_QUERY_MS=500
```

Diagnostic context contains:

- database driver;
- SQL statement with placeholders;
- binding count;
- execution duration in milliseconds;
- whether the query crossed the configured slow-query threshold.

Binding values are deliberately not logged. This keeps passwords, tokens, email addresses and other bound data out of query diagnostics.

Queries executed directly through native PDO remain native PDO operations and are not intercepted.

## Production defaults

The default production path remains low-overhead:

```dotenv
LOG_FORMAT=text
LOG_LEVEL=info
DB_LOG_QUERIES=false
DB_SLOW_QUERY_MS=0
```

Request IDs require no service or storage. Database timing is skipped when both query diagnostic options are disabled.
