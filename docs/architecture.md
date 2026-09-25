# Architecture

SedoPHP intentionally ships as a single repository during the 0.x series.

The repository contains both:

- the small framework core under `src/`;
- the starter application under `app/`, `routes/`, `config/` and `public/`.

This is deliberate. A single downloadable ZIP is easier to understand and deploy on ordinary shared hosting, including servers where Composer is not installed.

## Package strategy

The Composer package is currently named `sedophp/framework` and is a `project` package because the repository is directly usable as an application skeleton.

Before 1.0, the project may be split into separate core and starter packages only if that produces a clear user benefit without harming the Composer-free deployment path.

Until then, keeping the distribution in one repository is considered a feature rather than technical debt.

## Request lifecycle

```text
public/index.php
  -> bootstrap/app.php
  -> Application
  -> Request
  -> Router
  -> Middleware
  -> Controller / Closure
  -> Response
```

There is no service-provider layer and no dependency-injection container required for ordinary application code.

## Design constraints

1. Native PHP should remain visible.
2. Public APIs should stay small.
3. Hidden behavior must be rare.
4. Shared hosting must remain a first-class target.
5. Core features require tests before release.
