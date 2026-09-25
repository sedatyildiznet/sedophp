# Environment doctor — 0.3 development

`php sedo doctor` performs deployment and configuration checks without requiring any external service.

Checks are reported as:

- `PASS` — required or recommended configuration is available;
- `WARN` — the application can still run, but the configuration deserves attention;
- `FAIL` — a required runtime or deployment capability is missing;
- `INFO` — optional diagnostic information.

The command exits with status 1 only when one or more required checks fail.

## Checked areas

The 0.3 doctor covers:

- PHP 8.3+;
- PDO and the configured PDO driver;
- Fileinfo;
- `.env` presence;
- writable log and cache storage;
- local filesystem storage path;
- application environment and debug mode;
- APP_KEY presence and basic length guidance;
- secure session cookies for HTTPS application URLs;
- production query diagnostics;
- public entrypoint and shared-hosting `.htaccess` rules;
- Apache rewrite support when Apache exposes module information;
- live database connectivity;
- queue table availability;
- scheduler definition file;
- mail driver configuration;
- optional optimization cache status.

## Shared hosting

Doctor does not make terminal access a framework requirement. It is an optional verification tool when SSH or a hosting terminal is available.

Applications without shell access continue to run through the normal HTTP entrypoint.

## Example

```text
[PASS] PHP >= 8.3                  8.4.x
[PASS] PDO extension               loaded
[WARN] APP_KEY                     missing; signed URLs require APP_KEY
[PASS] database connection         connection successful
[INFO] optimization cache          not built (optional)
```

Warnings do not make the command fail.
